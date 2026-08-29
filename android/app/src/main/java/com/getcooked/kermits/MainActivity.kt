package com.getcooked.kermits

import android.app.DatePickerDialog
import android.app.TimePickerDialog

import android.content.Context
import android.net.Uri
import android.os.Bundle
import androidx.activity.compose.BackHandler
import androidx.activity.ComponentActivity
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.compose.setContent
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ReceiptLong
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.Remove
import androidx.compose.material.icons.filled.Search
import androidx.compose.material.icons.filled.Visibility
import androidx.compose.material.icons.filled.VisibilityOff
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.clearAndSetSemantics
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.sp
import androidx.compose.ui.unit.dp
import coil.compose.AsyncImage
import coil.request.ImageRequest
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.async
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.coroutineScope
import kotlinx.coroutines.launch
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.OkHttpClient
import okhttp3.RequestBody.Companion.toRequestBody
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.HttpException
import retrofit2.converter.moshi.MoshiConverterFactory
import com.squareup.moshi.Moshi
import com.squareup.moshi.JsonDataException
import com.squareup.moshi.JsonEncodingException
import java.io.IOException
import java.net.SocketTimeoutException
import java.util.Locale
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.concurrent.TimeUnit

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        val store = SessionStore(this)
        val log = HttpLoggingInterceptor().apply { level = HttpLoggingInterceptor.Level.BASIC }
        val clientBuilder = OkHttpClient.Builder()
            .connectTimeout(15, TimeUnit.SECONDS)
            .readTimeout(25, TimeUnit.SECONDS)
            .callTimeout(35, TimeUnit.SECONDS)
            .addInterceptor { chain ->
            chain.proceed(chain.request().newBuilder().apply { store.token?.let { header("Authorization", "Bearer $it") }; header("Accept", "application/json") }.build())
        }
        if (BuildConfig.DEBUG) clientBuilder.addInterceptor(log)
        val client = clientBuilder.build()
        val api = Retrofit.Builder().baseUrl(BuildConfig.API_BASE_URL).client(client).addConverterFactory(MoshiConverterFactory.create()).build().create(KermitsApi::class.java)
        setContent { KermitsTheme { KermitsApp(ViewModelProvider(this, AppViewModel.factory(api, store))[AppViewModel::class.java]) } }
    }
}

private val BRAND_LOGO_URL = BuildConfig.API_BASE_URL.substringBefore("/api/").trimEnd('/') + "/kermits-logo.jpg"

data class CheckoutDetails(
    val phone: String,
    val reservationAt: String,
    val tableSize: String,
    val paymentMethod: String,
    val paymentReference: String?,
    val notes: String,
    val proofUri: Uri?,
)

class AppViewModel(private val api: KermitsApi, private val store: SessionStore) : ViewModel() {
    var user by mutableStateOf<User?>(null); private set
    var products by mutableStateOf<List<Product>>(emptyList()); private set
    var orders by mutableStateOf<List<Order>>(emptyList()); private set
    var reservations by mutableStateOf<List<Reservation>>(emptyList()); private set
    var gcashQrUrl by mutableStateOf<String?>(null); private set
    var cart by mutableStateOf<Map<Int, Int>>(emptyMap()); private set
    var busy by mutableStateOf(false); private set
    var error by mutableStateOf<String?>(null); private set
    var registrationMessage by mutableStateOf<String?>(null); private set
    var signedIn by mutableStateOf(store.token != null && store.keepsSession); private set
    fun clearError() { error = null }
    init {
        if (signedIn) refresh() else store.clear()
    }
    fun login(login: String, password: String, keepSignedIn: Boolean) {
        if (busy || login.isBlank() || password.isBlank()) return
        busy = true
        error = null
        viewModelScope.launch {
            try {
                val response = api.login(LoginRequest(login.trim(), password))
                if (!response.isSuccessful) {
                    error = when {
                        response.code() == 429 -> "Too many login attempts. Please wait one minute and try again."
                        response.code() >= 500 -> "Kermit's server is temporarily unavailable. Please try again shortly."
                        else -> apiError(response.errorBody()?.string()) ?: "The username/email or password is incorrect. The mobile app accepts customer accounts only."
                    }
                    return@launch
                }
                val result = response.body()?.data
                if (result == null) {
                    error = "Kermit's returned an incomplete sign-in response. Please try again."
                    return@launch
                }
                store.saveSession(result.token, keepSignedIn)
                user = result.user
                signedIn = true
                try {
                    load()
                } catch (exception: CancellationException) {
                    throw exception
                } catch (_: Exception) {
                    error = "Signed in, but the latest menu could not be loaded. Pull down to refresh when you are online."
                }
            } catch (exception: CancellationException) {
                throw exception
            } catch (_: JsonDataException) {
                error = "Kermit's returned an invalid sign-in response. Please update the app and try again."
            } catch (_: JsonEncodingException) {
                error = "Kermit's returned an invalid sign-in response. Please update the app and try again."
            } catch (_: SocketTimeoutException) {
                error = "Kermit's server took too long to respond. Please try again."
            } catch (_: IOException) {
                error = "Kermit's server could not be reached. Please check that you have the latest app and try again."
            } catch (_: Exception) {
                error = "Sign-in could not be completed. Please try again."
            } finally {
                busy = false
            }
        }
    }
    fun logout() = viewModelScope.launch {
        runCatching { api.logout() }
        store.clear()
        user = null
        products = emptyList()
        orders = emptyList()
        reservations = emptyList()
        signedIn = false
    }
    fun refresh() = viewModelScope.launch {
        busy = true
        error = null
        try {
            // Validate a persisted token first. Previously an expired token kept the
            // app on its signed-in screen, making the login form inaccessible.
            user = api.me()["data"] ?: throw IllegalStateException("Missing account data")
            load()
        } catch (exception: HttpException) {
            if (exception.code() == 401) {
                store.clear()
                user = null
                products = emptyList()
                orders = emptyList()
                reservations = emptyList()
                signedIn = false
                error = "Your session has expired. Please log in again."
            } else {
                error = "Could not load the latest menu."
            }
        } catch (_: Exception) {
            error = "Could not load the latest menu. Check your internet connection."
        } finally {
            busy = false
        }
    }
    private suspend fun load() = coroutineScope {
        val catalogRequest = async { api.products().data }
        val ordersRequest = async { runCatching { api.orders().data }.getOrNull() }
        val reservationsRequest = async { runCatching { api.reservations().data }.getOrNull() }
        val catalog = catalogRequest.await()
        products = catalog.products
        gcashQrUrl = catalog.gcash_qr_url
        ordersRequest.await()?.let { orders = it }
        reservationsRequest.await()?.let { reservations = it }
    }
    fun sendCode(email: String, done: (String?) -> Unit) = viewModelScope.launch {
        busy = true
        error = null
        val normalizedEmail = email.trim().lowercase()
        try {
            val response = api.sendRegistrationCode(SendCodeRequest(normalizedEmail))
            if (!response.isSuccessful) {
                error = apiError(response.errorBody()?.string()) ?: "The verification code could not be sent. Please try again."
                done(null)
                return@launch
            }
            val challenge = response.body()?.data?.challenge
            if (challenge == null) {
                error = "Kermit's returned an incomplete verification response. Please try again."
                done(null)
                return@launch
            }
            registrationMessage = "Verification code sent to $normalizedEmail"
            done(challenge)
        } catch (_: Exception) {
            error = "Could not reach Kermit's server. Make sure this phone and the restaurant computer use the same Wi-Fi, then try again."
            done(null)
        } finally {
            busy = false
        }
    }
    fun requestPasswordReset(email: String, done: (String?) -> Unit) = viewModelScope.launch {
        if (busy) return@launch
        busy = true
        error = null
        try {
            val response = api.forgotPassword(ForgotPasswordRequest(email.trim().lowercase()))
            if (!response.isSuccessful) {
                error = apiError(response.errorBody()?.string()) ?: "The reset request could not be sent. Please try again."
                done(null)
                return@launch
            }
            done(response.body()?.message ?: "If that customer account exists, a password reset link has been sent.")
        } catch (_: Exception) {
            error = "Could not contact Kermit's. Check your connection and try again."
            done(null)
        } finally {
            busy = false
        }
    }
    fun verifyCode(challenge: String, email: String, code: String, done: (String?) -> Unit) = viewModelScope.launch { busy = true; error = null; try { val response = api.verifyRegistrationCode(VerifyCodeRequest(challenge, email.trim().lowercase(), code)); check(response.isSuccessful); done(response.body()?.data?.registration_token) } catch (_: Exception) { error = "The verification code is invalid or expired"; done(null) } finally { busy = false } }
    fun register(request: RegisterRequest, done: (Boolean) -> Unit) = viewModelScope.launch { busy = true; error = null; try { val response = api.register(request); check(response.isSuccessful); registrationMessage = "Account created. You can now log in."; done(true) } catch (_: Exception) { error = "Could not create the account. Check your details."; done(false) } finally { busy = false } }
    fun loadOrder(id: Int, done: (Order?) -> Unit) = viewModelScope.launch { busy = true; try { done(api.order(id).body()?.get("data")) } catch (_: Exception) { error = "Could not load this order"; done(null) } finally { busy = false } }
    fun loadReservation(id: Int, done: (Reservation?) -> Unit) = viewModelScope.launch { busy = true; try { done(api.reservation(id).body()?.get("data")) } catch (_: Exception) { error = "Could not load this reservation"; done(null) } finally { busy = false } }
    fun add(product: Product) { val count = (cart[product.id] ?: 0) + 1; if (count <= product.stock) cart = cart + (product.id to count) }
    fun remove(product: Product) { val count = (cart[product.id] ?: 0) - 1; cart = if (count > 0) cart + (product.id to count) else cart - product.id }
    fun placeOrder(context: Context, details: CheckoutDetails, done: (Boolean) -> Unit) = viewModelScope.launch {
        busy = true
        error = null
        try {
            val parts = mutableMapOf<String, okhttp3.RequestBody>(
                "payment_method" to details.paymentMethod.formPart(),
                "table_size" to details.tableSize.formPart(),
                "phone" to details.phone.formPart(),
                "reservation_at" to details.reservationAt.formPart(),
            )
            if (details.paymentMethod == "gcash" && !details.paymentReference.isNullOrBlank()) parts["payment_reference"] = details.paymentReference.formPart()
            if (details.notes.isNotBlank()) parts["notes"] = details.notes.formPart()
            cart.entries.forEachIndexed { index, entry ->
                parts["items[$index][product_id]"] = entry.key.toString().formPart()
                parts["items[$index][quantity]"] = entry.value.toString().formPart()
            }
            val proof = if (details.paymentMethod == "gcash") details.proofUri?.toMultipart(context, "payment_proof") else null
            val response = api.createOrder(parts, proof)
            if (!response.isSuccessful) {
                error = apiError(response.errorBody()?.string()) ?: "Order details are invalid."
                done(false)
                return@launch
            }
            cart = emptyMap()
            orders = api.orders().data
            reservations = api.reservations().data
            done(true)
        } catch (_: Exception) {
            error = "Unable to reach Kermit's. Check your internet connection."
            done(false)
        } finally {
            busy = false
        }
    }
    fun placeReservation(context: Context, type: String, phone: String, at: String, size: String, guests: String, notes: String, foodRequest: String, menuItems: Map<Int, Int>, payment: String, reference: String, proofUri: Uri?, done: (Boolean) -> Unit) = viewModelScope.launch {
        busy = true
        error = null
        try {
            val response = api.createReservation(
                type.formPart(),
                if (type == "table") size.formPart() else null,
                phone.formPart(),
                at.formPart(),
                if (type == "exclusive") guests.formPart() else null,
                foodRequest.takeIf { it.isNotBlank() }?.formPart(),
                payment.formPart(),
                reference.takeIf { payment == "gcash" && it.isNotBlank() }?.formPart(),
                if (payment == "gcash") proofUri?.toMultipart(context, "payment_proof") else null,
                menuItems.mapKeys { (productId, _) -> "menu_items[$productId]" }.mapValues { (_, quantity) -> quantity.toString().formPart() },
                notes.takeIf { it.isNotBlank() }?.formPart(),
            )
            if (!response.isSuccessful) { error = apiError(response.errorBody()?.string()) ?: "Reservation details are invalid"; done(false); return@launch }
            reservations = api.reservations().data
            done(true)
        } catch (_: Exception) { error = "Unable to reach Kermit's. Check your internet connection."; done(false) } finally { busy = false }
    }
    companion object {
        private fun apiError(body: String?): String? = body?.let { runCatching { Moshi.Builder().build().adapter(ApiError::class.java).fromJson(it) }.getOrNull() }?.let { apiError -> apiError.message ?: apiError.errors?.values?.flatten()?.firstOrNull() }
        fun factory(api: KermitsApi, store: SessionStore) = object : ViewModelProvider.Factory {
            @Suppress("UNCHECKED_CAST")
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                require(modelClass.isAssignableFrom(AppViewModel::class.java))

                return AppViewModel(api, store) as T
            }
        }
    }
}

@Composable fun KermitsTheme(content: @Composable () -> Unit) { MaterialTheme(colorScheme = lightColorScheme(primary = Color(0xFF737D00), onPrimary = Color.White, background = Color(0xFFEFEFEF), surface = Color.White, onSurface = Color(0xFF171817), surfaceVariant = Color(0xFFF4F5EE), onSurfaceVariant = Color(0xFF687064), outline = Color(0xFFD7DACF)), typography = Typography().copy(headlineLarge = Typography().headlineLarge.copy(fontWeight = FontWeight.Bold), headlineMedium = Typography().headlineMedium.copy(fontWeight = FontWeight.Bold)), content = content) }

@Composable
private fun BrandLogo(modifier: Modifier = Modifier) {
    val fallback = painterResource(R.drawable.ic_launcher)
    AsyncImage(
        model = BRAND_LOGO_URL,
        contentDescription = "Kermit's logo",
        modifier = modifier.clip(androidx.compose.foundation.shape.CircleShape),
        placeholder = fallback,
        error = fallback,
        contentScale = ContentScale.Crop,
    )
}

@Composable
fun KermitsApp(vm: AppViewModel) {
    var login by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var registering by remember { mutableStateOf(false) }
    var recoveringPassword by remember { mutableStateOf(false) }
    var tab by rememberSaveable(vm.signedIn) { mutableIntStateOf(0) }
    var payment by remember { mutableStateOf("cash") }
    var orderMessage by remember { mutableStateOf<String?>(null) }
    var selectedOrder by remember { mutableStateOf<Order?>(null) }
    var selectedReservation by remember { mutableStateOf<Reservation?>(null) }
    if (!vm.signedIn) {
        when {
            recoveringPassword -> PasswordRecoveryScreen(vm, onBack = { recoveringPassword = false })
            registering -> RegistrationScreen(vm, onBack = { registering = false })
            else -> LoginScreen(vm, login, { login = it }, password, { password = it }, onRegister = { vm.clearError(); registering = true }, onForgotPassword = { vm.clearError(); recoveringPassword = true })
        }
        return
    }
    BackHandler(enabled = tab != 0) { tab = 0 }
    Scaffold(
        containerColor = Color(0xFFEFEFEF),
        bottomBar = { CustomerBottomBar(tab) { tab = it } },
    ) { padding ->
        Column(Modifier.padding(padding).fillMaxSize().padding(horizontal = 14.dp, vertical = 12.dp)) {
            if (vm.busy) LinearProgressIndicator(Modifier.fillMaxWidth().height(2.dp), color = Color(0xFFB5C019), trackColor = Color.Transparent)
            orderMessage?.let { Text(it, color = MaterialTheme.colorScheme.primary, modifier = Modifier.padding(top = 12.dp)) }
            vm.error?.let { Text(it, color = MaterialTheme.colorScheme.error, modifier = Modifier.padding(top = 8.dp)) }
            Spacer(Modifier.height(8.dp))
            Box(Modifier.fillMaxWidth().weight(1f)) {
                when (tab) {
                    0 -> MenuScreen(vm, payment, { payment = it }, { message -> orderMessage = message }, onReserve = { tab = 2 })
                    1 -> CustomerHistoryScreen(vm, onOrder = { vm.loadOrder(it) { selectedOrder = it } }, onReservation = { vm.loadReservation(it) { selectedReservation = it } }, onReserve = { tab = 2 })
                    2 -> Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState())) { ReservationScreen(vm, onDetail = { id -> vm.loadReservation(id) { selectedReservation = it } }) { message -> orderMessage = message } }
                    else -> AccountScreen(vm)
                }
            }
        }
    }
    selectedOrder?.let { order -> DetailDialog("Order #${order.id}", "${money(order.total)} · ${order.payment_status}", order.items.map { item -> "${item.quantity} × ${item.name}  ${money(item.subtotal)}" } + listOf("Payment: ${order.payment_method}${order.payment_reference?.let { " · Ref $it" } ?: ""}") + (order.reservation?.let { reservation -> listOf("Table: ${reservation.table_size}-seater", "Schedule: ${reservation.reservation_at}", "Reservation fee: ${money(reservation.total_amount)}") } ?: emptyList())) { selectedOrder = null } }
    selectedReservation?.let { reservation -> DetailDialog("Reservation ${reservation.reference}", "${reservation.status} · ${money(reservation.total_amount)}", listOf("${reservation.type} · ${reservation.guests ?: reservation.table_size} guest(s)", reservation.reservation_at, "Payment: ${reservation.payment_method} · ${reservation.payment_status}${reservation.payment_reference?.let { " · Ref $it" } ?: ""}", "Reservation fee: ${money(reservation.reservation_fee)}", "Food total: ${money(reservation.food_total)}") + reservation.items.map { item -> "${item.quantity} × ${item.name}  ${money(item.subtotal)}" }) { selectedReservation = null } }
}

@Composable
private fun LoginScreen(vm: AppViewModel, login: String, setLogin: (String) -> Unit, password: String, setPassword: (String) -> Unit, onRegister: () -> Unit, onForgotPassword: () -> Unit) {
    BoxWithConstraints(Modifier.fillMaxSize().background(Color(0xFFF5F5EF))) {
        val wide = maxWidth >= 600.dp
        if (wide) Row(Modifier.fillMaxSize()) {
            BrandPanel(Modifier.weight(0.96f).fillMaxHeight())
            LoginForm(vm, login, setLogin, password, setPassword, onRegister, onForgotPassword, Modifier.weight(1.04f).fillMaxHeight())
        } else Column(Modifier.fillMaxSize()) {
            BrandPanel(Modifier.fillMaxWidth().height(170.dp))
            LoginForm(vm, login, setLogin, password, setPassword, onRegister, onForgotPassword, Modifier.fillMaxWidth().weight(1f))
        }
    }
}

@Composable
private fun BrandPanel(modifier: Modifier) {
    BoxWithConstraints(modifier.background(Brush.linearGradient(listOf(Color(0xFF131413), Color(0xFF1C1E1A), Color(0xFF30332B))))) {
        val compact = maxHeight <= 180.dp
        Column(Modifier.fillMaxSize().padding(horizontal = if (compact) 22.dp else 28.dp, vertical = if (compact) 18.dp else 30.dp)) {
        BrandLogo(Modifier.size(if (compact) 58.dp else 84.dp).background(Color.White, androidx.compose.foundation.shape.CircleShape).padding(5.dp))
        Column(Modifier.weight(1f), verticalArrangement = Arrangement.Center) {
            Text("RESTAURANT POS", color = Color(0xFFAAB514), fontSize = 12.sp, letterSpacing = 1.8.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(if (compact) 4.dp else 10.dp))
            Text("Simple tools for\nbetter service.", color = Color.White, fontSize = if (compact) 25.sp else 34.sp, lineHeight = if (compact) 27.sp else 37.sp, fontWeight = FontWeight.Bold)
            if (!compact) {
                Spacer(Modifier.height(14.dp))
                Text("Manage sales, products, inventory, reports, and receipts from one reliable system.", color = Color(0xFFB9BCB5), fontSize = 15.sp, lineHeight = 23.sp)
            }
        }
        if (!compact) Text("Time-honored recipes since 2000", color = Color(0xFF858982), fontSize = 12.sp)
        }
    }
}

@Composable
private fun LoginForm(vm: AppViewModel, login: String, setLogin: (String) -> Unit, password: String, setPassword: (String) -> Unit, onRegister: () -> Unit, onForgotPassword: () -> Unit, modifier: Modifier) {
    var keepSignedIn by remember { mutableStateOf(true) }
    var passwordVisible by remember { mutableStateOf(false) }
    val canLogIn = !vm.busy && login.isNotBlank() && password.isNotBlank()
    val submitLogin = { if (canLogIn) vm.login(login, password, keepSignedIn) }
    Column(modifier.background(Color(0xFFF7F7F1)).padding(horizontal = 26.dp, vertical = 34.dp), verticalArrangement = Arrangement.Center) {
        Column(Modifier.fillMaxWidth().widthIn(max = 520.dp).align(Alignment.CenterHorizontally)) {
            Text("WELCOME BACK", color = Color(0xFFAAB514), fontSize = 12.sp, letterSpacing = 1.8.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(8.dp)); Text("Log in to your account", color = Color(0xFF202124), fontSize = 30.sp, lineHeight = 35.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(7.dp)); Text("Enter your details to continue to Kermit’s.", color = Color(0xFF687286), fontSize = 15.sp)
            Spacer(Modifier.height(28.dp))
            OutlinedTextField(login, setLogin, label = { Text("Username or email address") }, placeholder = { Text("Username or name@gmail.com") }, singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email, imeAction = ImeAction.Next), colors = loginFieldColors(), shape = RoundedCornerShape(13.dp), modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(16.dp)); OutlinedTextField(password, setPassword, label = { Text("Password") }, placeholder = { Text("Enter your password") }, singleLine = true, visualTransformation = if (passwordVisible) androidx.compose.ui.text.input.VisualTransformation.None else PasswordVisualTransformation(), keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password, imeAction = ImeAction.Done), keyboardActions = KeyboardActions(onDone = { submitLogin() }), trailingIcon = { IconButton(onClick = { passwordVisible = !passwordVisible }) { Icon(if (passwordVisible) Icons.Default.VisibilityOff else Icons.Default.Visibility, if (passwordVisible) "Hide password" else "Show password") } }, colors = loginFieldColors(), shape = RoundedCornerShape(13.dp), modifier = Modifier.fillMaxWidth())
            vm.error?.let { Text(it, color = MaterialTheme.colorScheme.error, fontSize = 13.sp, modifier = Modifier.padding(top = 12.dp)) }
            Spacer(Modifier.height(18.dp)); Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) { Row(verticalAlignment = Alignment.CenterVertically) { Checkbox(checked = keepSignedIn, onCheckedChange = { keepSignedIn = it }); Text("Keep me signed in", color = Color(0xFF687286), fontSize = 13.sp) }; TextButton(onClick = onForgotPassword, contentPadding = PaddingValues(horizontal = 4.dp, vertical = 0.dp)) { Text("Forgot password?", color = Color(0xFF626B00), fontSize = 13.sp, fontWeight = FontWeight.Bold) } }
            Spacer(Modifier.height(15.dp)); Button(onClick = submitLogin, enabled = canLogIn, shape = RoundedCornerShape(13.dp), colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF171817), contentColor = Color.White), modifier = Modifier.fillMaxWidth().height(56.dp)) { Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) { Text(if (vm.busy) "Signing in..." else "Log in", fontWeight = FontWeight.Bold, fontSize = 16.sp); Text("→", fontSize = 22.sp) } }
            Spacer(Modifier.height(18.dp)); TextButton(onClick = onRegister, modifier = Modifier.fillMaxWidth()) { Text("New customer? Create an account", color = Color(0xFF626B00), fontSize = 13.sp, fontWeight = FontWeight.Bold) }
        }
    }
}

@Composable
private fun CustomerBottomBar(selected: Int, select: (Int) -> Unit) {
    val destinations = listOf(
        Triple("Menu", Icons.Default.Home, 0),
        Triple("History", Icons.AutoMirrored.Filled.ReceiptLong, 1),
        Triple("Reserve", Icons.Default.CalendarMonth, 2),
        Triple("Account", Icons.Default.Person, 3),
    )

    NavigationBar(
        containerColor = Color(0xFF202124),
        contentColor = Color.White,
        tonalElevation = 10.dp,
    ) {
        destinations.forEach { (label, icon, index) ->
            NavigationBarItem(
                selected = selected == index,
                onClick = { select(index) },
                icon = { Icon(icon, contentDescription = null) },
                label = { Text(label, fontSize = 11.sp, fontWeight = FontWeight.Bold) },
                alwaysShowLabel = true,
                colors = NavigationBarItemDefaults.colors(
                    selectedIconColor = Color(0xFF202124),
                    selectedTextColor = Color.White,
                    indicatorColor = Color(0xFFB5C019),
                    unselectedIconColor = Color(0xFFB7BAB5),
                    unselectedTextColor = Color(0xFFB7BAB5),
                ),
            )
        }
    }
}

@Composable
private fun AccountScreen(vm: AppViewModel) {
    Column(Modifier.fillMaxSize()) {
        Text("Account", fontSize = 30.sp, fontWeight = FontWeight.Black)
        Text("Your Kermit's customer profile", color = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.padding(top = 4.dp, bottom = 18.dp))
        Surface(Modifier.fillMaxWidth(), shape = RoundedCornerShape(8.dp), color = Color.White, border = androidx.compose.foundation.BorderStroke(1.dp, Color(0xFFD7DACF))) {
            Column(Modifier.padding(18.dp)) {
                Text(vm.user?.name.orEmpty(), fontSize = 20.sp, fontWeight = FontWeight.Bold)
                Text(vm.user?.email.orEmpty(), color = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.padding(top = 5.dp))
                vm.user?.phone?.let { Text(it, color = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.padding(top = 3.dp)) }
                Spacer(Modifier.height(20.dp))
                Button(onClick = vm::logout, colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF171817)), shape = RoundedCornerShape(7.dp), modifier = Modifier.fillMaxWidth().height(48.dp)) { Text("Log out", fontWeight = FontWeight.Bold) }
            }
        }
    }
}

@Composable
private fun PasswordRecoveryScreen(vm: AppViewModel, onBack: () -> Unit) {
    var email by remember { mutableStateOf("") }
    var message by remember { mutableStateOf<String?>(null) }
    val validEmail = android.util.Patterns.EMAIL_ADDRESS.matcher(email.trim()).matches()

    Column(Modifier.fillMaxSize().background(Color(0xFFF7F7F1)).padding(horizontal = 26.dp, vertical = 28.dp)) {
        TextButton(onClick = onBack, contentPadding = PaddingValues(0.dp)) { Text("← Back to log in", color = Color(0xFF626B00), fontWeight = FontWeight.Bold) }
        Column(Modifier.fillMaxWidth().widthIn(max = 520.dp).weight(1f).align(Alignment.CenterHorizontally), verticalArrangement = Arrangement.Center) {
            BrandLogo(Modifier.size(72.dp).align(Alignment.CenterHorizontally).background(Color.White, androidx.compose.foundation.shape.CircleShape).padding(5.dp))
            Spacer(Modifier.height(22.dp))
            Text("RESET PASSWORD", color = Color(0xFFAAB514), fontSize = 12.sp, letterSpacing = 1.8.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(8.dp))
            Text("Recover your account", color = Color(0xFF202124), fontSize = 30.sp, lineHeight = 35.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(7.dp))
            Text("Enter the email address used by your customer account. We’ll email you a secure reset link.", color = Color(0xFF687286), fontSize = 14.sp, lineHeight = 21.sp)
            Spacer(Modifier.height(24.dp))
            OutlinedTextField(email, { email = it; message = null }, label = { Text("Email address") }, placeholder = { Text("name@gmail.com") }, singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email, imeAction = ImeAction.Done), keyboardActions = KeyboardActions(onDone = { if (validEmail && !vm.busy) vm.requestPasswordReset(email) { message = it } }), colors = loginFieldColors(), shape = RoundedCornerShape(13.dp), modifier = Modifier.fillMaxWidth())
            vm.error?.let { Text(it, color = MaterialTheme.colorScheme.error, fontSize = 13.sp, modifier = Modifier.padding(top = 12.dp)) }
            message?.let { Text(it, color = Color(0xFF626B00), fontSize = 13.sp, lineHeight = 19.sp, modifier = Modifier.padding(top = 12.dp)) }
            Spacer(Modifier.height(18.dp))
            Button(onClick = { vm.requestPasswordReset(email) { message = it } }, enabled = validEmail && !vm.busy, shape = RoundedCornerShape(13.dp), colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF171817), contentColor = Color.White), modifier = Modifier.fillMaxWidth().height(54.dp)) { Text(if (vm.busy) "Sending..." else "Send reset link", fontWeight = FontWeight.Bold) }
        }
    }
}

@Composable
private fun RegistrationScreen(vm: AppViewModel, onBack: () -> Unit) {
    var email by remember { mutableStateOf("") }; var challenge by remember { mutableStateOf<String?>(null) }; var code by remember { mutableStateOf("") }; var token by remember { mutableStateOf<String?>(null) }
    var name by remember { mutableStateOf("") }; var username by remember { mutableStateOf("") }; var phone by remember { mutableStateOf("") }; var password by remember { mutableStateOf("") }; var confirmation by remember { mutableStateOf("") }
    val validGmail = email.trim().matches(Regex("^[^@\\s]+@gmail\\.com$", RegexOption.IGNORE_CASE))
    Column(Modifier.fillMaxSize().background(Color(0xFFF7F7F1))) {
        RegistrationBrandPanel(Modifier.fillMaxWidth().height(170.dp))
        Column(Modifier.fillMaxWidth().weight(1f).verticalScroll(rememberScrollState()).padding(horizontal = 20.dp, vertical = 24.dp)) {
        TextButton(onClick = onBack, contentPadding = PaddingValues(0.dp)) { Text("← Back to log in", color = Color(0xFF626B00), fontWeight = FontWeight.Bold) }
        Spacer(Modifier.height(12.dp)); Text("SIGN UP", color = Color(0xFFAAB514), fontSize = 12.sp, letterSpacing = 1.8.sp, fontWeight = FontWeight.Bold); Text("Create your account", fontSize = 30.sp, fontWeight = FontWeight.Bold); Text("Verify your Gmail first, then create your customer account securely.", color = Color(0xFF687286), modifier = Modifier.padding(top = 7.dp))
        Spacer(Modifier.height(22.dp)); Text("Step 1  Gmail verification", fontWeight = FontWeight.Bold); Text("Use a Gmail address you can open now.", color = Color(0xFF687286), fontSize = 12.sp, modifier = Modifier.padding(top = 3.dp)); Spacer(Modifier.height(10.dp))
        OutlinedTextField(email, { email = it }, label = { Text("Gmail address") }, placeholder = { Text("name@gmail.com") }, enabled = challenge == null, singleLine = true, colors = loginFieldColors(), shape = RoundedCornerShape(12.dp), modifier = Modifier.fillMaxWidth())
        Spacer(Modifier.height(8.dp)); OutlinedButton(onClick = { vm.sendCode(email) { issuedChallenge -> challenge = issuedChallenge } }, enabled = challenge == null && validGmail && !vm.busy, shape = RoundedCornerShape(10.dp), modifier = Modifier.fillMaxWidth().height(48.dp)) { Text(if (vm.busy) "Sending..." else "Send code", fontWeight = FontWeight.Bold) }
        if (challenge != null && token == null) { Spacer(Modifier.height(12.dp)); OutlinedTextField(code, { code = it.filter(Char::isDigit).take(6) }, label = { Text("6-digit verification code") }, singleLine = true, colors = loginFieldColors(), shape = RoundedCornerShape(12.dp), modifier = Modifier.fillMaxWidth()); Spacer(Modifier.height(8.dp)); Button(onClick = { vm.verifyCode(challenge!!, email, code) { verified -> token = verified } }, enabled = code.length == 6 && !vm.busy, colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF171817)), shape = RoundedCornerShape(10.dp), modifier = Modifier.fillMaxWidth().height(48.dp)) { Text("Verify Gmail", fontWeight = FontWeight.Bold) } }
        if (token != null) { Spacer(Modifier.height(22.dp)); Text("Step 2  Account details", fontWeight = FontWeight.Bold); Spacer(Modifier.height(10.dp)); RegistrationField("Full name", name) { name = it }; RegistrationField("Username", username) { username = it }; RegistrationField("Phone number", phone) { phone = it }; RegistrationField("Password", password, true) { password = it }; RegistrationField("Confirm password", confirmation, true) { confirmation = it }; Spacer(Modifier.height(12.dp)); Button(onClick = { vm.register(RegisterRequest(token!!, name, username, email, phone, password, confirmation)) { ok -> if (ok) onBack() } }, enabled = !vm.busy && name.isNotBlank() && username.length >= 3 && phone.matches(Regex("09\\d{9}")) && password.length >= 12 && password == confirmation, modifier = Modifier.fillMaxWidth()) { Text("Create account") } }
        vm.error?.let { Text(it, color = MaterialTheme.colorScheme.error, fontSize = 13.sp, modifier = Modifier.padding(top = 12.dp)) }; vm.registrationMessage?.let { Text(it, color = MaterialTheme.colorScheme.primary, fontSize = 13.sp, modifier = Modifier.padding(top = 12.dp)) }
        }
    }
}

@Composable
private fun RegistrationBrandPanel(modifier: Modifier = Modifier) {
    Row(modifier.background(Brush.linearGradient(listOf(Color(0xFF131413), Color(0xFF1C1E1A), Color(0xFF30332B)))).padding(22.dp), verticalAlignment = Alignment.CenterVertically) {
        BrandLogo(Modifier.size(62.dp).background(Color.White, androidx.compose.foundation.shape.CircleShape).padding(5.dp))
        Spacer(Modifier.width(18.dp))
        Column {
            Text("CUSTOMER ACCOUNT", color = Color(0xFFAAB514), fontSize = 11.sp, letterSpacing = 1.6.sp, fontWeight = FontWeight.Bold)
            Text("Order your\nfavorites.", color = Color.White, fontSize = 27.sp, lineHeight = 29.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(top = 5.dp))
        }
    }
}

@Composable private fun RegistrationField(label: String, value: String, password: Boolean = false, onChange: (String) -> Unit) { OutlinedTextField(value, onChange, label = { Text(label) }, singleLine = true, visualTransformation = if (password) PasswordVisualTransformation() else androidx.compose.ui.text.input.VisualTransformation.None, colors = loginFieldColors(), modifier = Modifier.fillMaxWidth().padding(bottom = 10.dp)) }

@Composable
private fun loginFieldColors() = OutlinedTextFieldDefaults.colors(
    focusedBorderColor = Color(0xFF8C960C), unfocusedBorderColor = Color(0xFFD5D7CC),
    focusedLabelColor = Color(0xFF737D00), unfocusedLabelColor = Color(0xFF687286),
    focusedTextColor = Color(0xFF202124), unfocusedTextColor = Color(0xFF202124),
    cursorColor = Color(0xFF737D00), focusedContainerColor = Color.White, unfocusedContainerColor = Color.White
)

@Composable
private fun DateTimePickerField(value: String, label: String, placeholder: String, guidance: String, onClick: () -> Unit) {
    val shape = RoundedCornerShape(11.dp)
    val accessibilityLabel = if (value.isBlank()) listOf(label, placeholder, guidance).joinToString(". ") else "$label. Selected $value"

    Box(Modifier.fillMaxWidth()) {
        OutlinedTextField(
            value = value,
            onValueChange = {},
            label = { Text(label) },
            placeholder = { Text(placeholder) },
            supportingText = { Text(guidance) },
            readOnly = true,
            singleLine = true,
            trailingIcon = { Icon(Icons.Default.CalendarMonth, contentDescription = null) },
            colors = loginFieldColors(),
            shape = shape,
            modifier = Modifier.fillMaxWidth().clearAndSetSemantics {},
        )
        Box(
            Modifier.matchParentSize()
                .clip(shape)
                .clickable(role = Role.Button, onClick = onClick)
                .semantics { contentDescription = accessibilityLabel },
        )
    }
}

private fun showDateTimePicker(context: Context, calendar: Calendar, dateFormat: SimpleDateFormat, onSelected: (String) -> Unit) {
    DatePickerDialog(
        context,
        { _, year, month, day ->
            calendar.set(year, month, day)
            TimePickerDialog(
                context,
                { _, hour, minute ->
                    calendar.set(Calendar.HOUR_OF_DAY, hour)
                    calendar.set(Calendar.MINUTE, minute)
                    onSelected(dateFormat.format(calendar.time))
                },
                calendar.get(Calendar.HOUR_OF_DAY),
                calendar.get(Calendar.MINUTE),
                false,
            ).show()
        },
        calendar.get(Calendar.YEAR),
        calendar.get(Calendar.MONTH),
        calendar.get(Calendar.DAY_OF_MONTH),
    ).apply {
        datePicker.minDate = System.currentTimeMillis()
    }.show()
}

@Composable
private fun MenuScreen(vm: AppViewModel, payment: String, setPayment: (String) -> Unit, setMessage: (String) -> Unit, onReserve: () -> Unit) {
    var query by remember { mutableStateOf("") }
    var category by remember { mutableStateOf("All") }
    var checkingOut by remember { mutableStateOf(false) }
    var phone by remember { mutableStateOf(vm.user?.phone.orEmpty()) }
    var date by remember { mutableStateOf("") }
    var tableSize by remember { mutableStateOf("4") }
    var notes by remember { mutableStateOf("") }
    var paymentReference by remember { mutableStateOf("") }
    var proofUri by remember { mutableStateOf<Uri?>(null) }
    val context = androidx.compose.ui.platform.LocalContext.current
    val calendar = remember { Calendar.getInstance() }
    val dateFormat = remember { SimpleDateFormat("yyyy-MM-dd HH:mm", Locale.US) }
    val proofPicker = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri -> proofUri = uri }
    val categories = remember(vm.products) { listOf("All") + vm.products.mapNotNull { it.category }.distinct() }
    val filtered = remember(vm.products, category, query) {
        vm.products.filter { product ->
            (category == "All" || product.category == category) &&
                (query.isBlank() || product.name.contains(query, ignoreCase = true) || product.description.orEmpty().contains(query, ignoreCase = true))
        }
    }
    val productsById = remember(vm.products) { vm.products.associateBy(Product::id) }
    val cartTotal = vm.cart.entries.sumOf { (productId, quantity) -> productsById[productId]?.price?.times(quantity) ?: 0.0 }
    val canPay = payment == "cash" || (paymentReference.length == 13 && proofUri != null)
    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(bottom = 20.dp)) {
    item(key = "menu-header") { Column {
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) { Text("Menu", fontSize = 30.sp, fontWeight = FontWeight.Black); Button(onClick = onReserve, shape = RoundedCornerShape(10.dp), colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF202124)), contentPadding = PaddingValues(horizontal = 16.dp, vertical = 10.dp)) { Text("Reserve", fontSize = 13.sp, fontWeight = FontWeight.ExtraBold) } }
    Spacer(Modifier.height(14.dp)); OutlinedTextField(query, { query = it }, placeholder = { Text("Search products", fontWeight = FontWeight.SemiBold) }, leadingIcon = { Icon(Icons.Default.Search, null, tint = Color(0xFF5E5968)) }, singleLine = true, colors = OutlinedTextFieldDefaults.colors(focusedContainerColor = Color.White, unfocusedContainerColor = Color.White, focusedBorderColor = Color(0xFFB5C019), unfocusedBorderColor = Color.Transparent), modifier = Modifier.fillMaxWidth().height(56.dp), shape = RoundedCornerShape(50.dp))
    Spacer(Modifier.height(14.dp)); Row(Modifier.horizontalScroll(rememberScrollState())) { categories.forEach { value -> MenuCategoryButton(selected = category == value, label = value, onClick = { category = value }) } }
    Spacer(Modifier.height(10.dp))
    } }
    filtered.groupBy { it.category ?: "Favorites" }.forEach { (category, categoryProducts) ->
        item(key = "category-$category") { Text(category, fontSize = 21.sp, fontWeight = FontWeight.Bold, color = Color(0xFF171817), modifier = Modifier.padding(vertical = 10.dp)) }
        items(categoryProducts, key = Product::id) { product ->
            Surface(Modifier.fillMaxWidth().padding(bottom = 12.dp), shape = RoundedCornerShape(12.dp), color = Color.White) { Column {
                if (product.image_url != null) AsyncImage(remember(product.image_url) { ImageRequest.Builder(context).data(product.image_url).size(900, 426).crossfade(true).build() }, product.name, Modifier.fillMaxWidth().padding(start = 12.dp, end = 12.dp, top = 12.dp).height(128.dp).clip(RoundedCornerShape(8.dp)), contentScale = ContentScale.Crop) else Box(Modifier.fillMaxWidth().padding(start = 12.dp, end = 12.dp, top = 12.dp).height(128.dp).clip(RoundedCornerShape(8.dp)).background(Color(0xFFE9ECD4)), contentAlignment = Alignment.Center) { Text(product.name.take(1), color = Color(0xFF747D00), fontSize = 42.sp, fontWeight = FontWeight.Bold) }
                Column(Modifier.padding(12.dp)) {
                    Text(product.name, fontSize = 15.sp, fontWeight = FontWeight.ExtraBold)
                    Text(product.description.orEmpty(), maxLines = 2, color = Color(0xFF6D746B), fontSize = 12.sp, lineHeight = 16.sp, modifier = Modifier.padding(top = 5.dp))
                    Row(Modifier.fillMaxWidth().padding(top = 10.dp), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                        Column { Text(money(product.price), fontSize = 16.sp, fontWeight = FontWeight.ExtraBold); Text("${product.stock} available", color = Color(0xFF596273), fontSize = 11.sp, fontWeight = FontWeight.SemiBold) }
                        Row(verticalAlignment = Alignment.CenterVertically) { val quantity = vm.cart[product.id] ?: 0; if (quantity > 0) { IconButton(onClick = { vm.remove(product) }, Modifier.size(34.dp)) { Icon(Icons.Default.Remove, "Remove", Modifier.size(18.dp)) }; Text(quantity.toString(), fontWeight = FontWeight.Bold, modifier = Modifier.padding(horizontal = 4.dp)) }; IconButton(onClick = { vm.add(product) }, enabled = quantity < product.stock, modifier = Modifier.size(34.dp)) { Icon(Icons.Default.Add, "Add", Modifier.size(19.dp)) } }
                    }
                }
            } }
        }
    }
    if (vm.cart.isNotEmpty()) {
        item(key = "cart-checkout") { Surface(Modifier.fillMaxWidth().padding(top = 8.dp), shape = RoundedCornerShape(14.dp), color = Color(0xFFF8F7F1), contentColor = Color(0xFF171817), border = androidx.compose.foundation.BorderStroke(1.dp, Color(0xFFDDE0D6))) {
            Column(Modifier.padding(16.dp)) {
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) { Text("${vm.cart.values.sum()} item(s) in your order", fontWeight = FontWeight.Bold); Text(money(cartTotal)) }
                if (!checkingOut) {
                    Spacer(Modifier.height(10.dp))
                    Button(onClick = { checkingOut = true }, shape = RoundedCornerShape(50.dp), modifier = Modifier.fillMaxWidth().height(44.dp), colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF5800F0), contentColor = Color.White)) { Text("Place Order", fontWeight = FontWeight.ExtraBold) }
                } else {
                    Spacer(Modifier.height(12.dp))
                    Text("ORDER CHECKOUT", color = Color(0xFF747D00), fontSize = 10.sp, letterSpacing = 1.4.sp, fontWeight = FontWeight.ExtraBold)
                    Text("Reserve a table", fontSize = 23.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(top = 5.dp, bottom = 3.dp))
                    Text("Your selected food is already in the order, so only the table details are needed here.", color = Color(0xFF6E746B), fontSize = 12.sp, lineHeight = 18.sp)
                    Spacer(Modifier.height(14.dp))
                    OutlinedTextField(phone, { phone = it.filter(Char::isDigit).take(11) }, label = { Text("Phone number") }, supportingText = { Text("11 digits starting with 09") }, singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number), modifier = Modifier.fillMaxWidth(), colors = loginFieldColors(), shape = RoundedCornerShape(11.dp))
                    Spacer(Modifier.height(8.dp))
                    DateTimePickerField(date, "Date and time", "Choose your schedule", "Select a date first, then choose a time") { showDateTimePicker(context, calendar, dateFormat) { date = it } }
                    Spacer(Modifier.height(8.dp))
                    Row(Modifier.horizontalScroll(rememberScrollState())) { listOf("1", "2", "4", "8", "12").forEach { value -> FilterChip(selected = tableSize == value, onClick = { tableSize = value }, label = { Text("$value seats") }, modifier = Modifier.padding(end = 6.dp)) } }
                    Spacer(Modifier.height(8.dp))
                    OutlinedTextField(notes, { notes = it.take(2000) }, label = { Text("Additional notes (optional)") }, minLines = 2, modifier = Modifier.fillMaxWidth(), colors = loginFieldColors(), shape = RoundedCornerShape(11.dp))
                    Spacer(Modifier.height(10.dp))
                    Row(verticalAlignment = Alignment.CenterVertically) { Text("Payment:"); Spacer(Modifier.width(8.dp)); FilterChip(selected = payment == "cash", onClick = { setPayment("cash") }, label = { Text("Cash") }); Spacer(Modifier.width(6.dp)); FilterChip(selected = payment == "gcash", onClick = { setPayment("gcash") }, label = { Text("GCash") }) }
                    if (payment == "gcash") {
                        vm.gcashQrUrl?.let { AsyncImage(it, "GCash QR code", Modifier.fillMaxWidth().height(150.dp).padding(vertical = 8.dp), contentScale = ContentScale.Inside) }
                        OutlinedTextField(paymentReference, { paymentReference = it.filter(Char::isDigit).take(13) }, label = { Text("13-digit GCash reference") }, singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number), modifier = Modifier.fillMaxWidth(), colors = loginFieldColors(), shape = RoundedCornerShape(11.dp))
                        Spacer(Modifier.height(8.dp))
                        OutlinedButton(onClick = { proofPicker.launch("image/*") }, modifier = Modifier.fillMaxWidth()) { Text(proofUri?.lastPathSegment ?: "Attach payment proof") }
                    }
                    Spacer(Modifier.height(12.dp))
                    Button(onClick = { vm.placeOrder(context, CheckoutDetails(phone, date, tableSize, payment, paymentReference, notes, proofUri)) { ok -> setMessage(if (ok) "Order and table request submitted." else "Order could not be submitted.") } }, enabled = !vm.busy && phone.matches(Regex("09\\d{9}")) && date.isNotBlank() && canPay, shape = RoundedCornerShape(11.dp), modifier = Modifier.fillMaxWidth().height(50.dp), colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF171817), contentColor = Color.White)) { Text(if (vm.busy) "Submitting..." else "Confirm payment & view receipt", fontWeight = FontWeight.Bold) }
                }
            }
        } }
    }
    }
}

@Composable
private fun MenuCategoryButton(selected: Boolean, label: String, onClick: () -> Unit) {
    Button(onClick = onClick, shape = RoundedCornerShape(11.dp), colors = ButtonDefaults.buttonColors(containerColor = if (selected) Color(0xFF202124) else Color.White, contentColor = if (selected) Color.White else Color(0xFF171817)), contentPadding = PaddingValues(horizontal = 20.dp), modifier = Modifier.padding(end = 10.dp).height(44.dp)) { Text(label, fontSize = 14.sp, fontWeight = FontWeight.ExtraBold) }
}

@Composable
private fun CustomerHistoryScreen(vm: AppViewModel, onOrder: (Int) -> Unit, onReservation: (Int) -> Unit, onReserve: () -> Unit) {
    var activityTab by remember { mutableIntStateOf(0) }
    val activeReservations = remember(vm.reservations) { vm.reservations.count { it.status in listOf("pending", "confirmed") } }
    val paidOrders = remember(vm.orders) { vm.orders.count { it.payment_status == "paid" } }
    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(bottom = 28.dp)) {
        item(key = "history-head") {
            Text("MY ACTIVITY", color = Color(0xFF777F00), fontSize = 11.sp, letterSpacing = 1.5.sp, fontWeight = FontWeight.ExtraBold)
            Row(Modifier.fillMaxWidth().padding(top = 6.dp), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Text("Reservations and\npurchases", fontSize = 30.sp, lineHeight = 33.sp, fontWeight = FontWeight.Black)
                Button(onClick = onReserve, shape = RoundedCornerShape(7.dp), colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF171817)), contentPadding = PaddingValues(horizontal = 14.dp, vertical = 10.dp)) { Text("New reservation", fontSize = 12.sp, fontWeight = FontWeight.Bold) }
            }
            Surface(Modifier.fillMaxWidth().padding(top = 18.dp), shape = RoundedCornerShape(8.dp), color = Color.Transparent, border = androidx.compose.foundation.BorderStroke(1.dp, Color(0xFFD6D9CE))) {
                Column {
                    Row(Modifier.fillMaxWidth()) {
                        HistoryMetric("Reservations", vm.reservations.size, Modifier.weight(1f))
                        HistoryMetric("Active requests", activeReservations, Modifier.weight(1f))
                    }
                    HorizontalDivider(color = Color(0xFFD6D9CE))
                    Row(Modifier.fillMaxWidth()) {
                        HistoryMetric("Purchases", vm.orders.size, Modifier.weight(1f))
                        HistoryMetric("Paid orders", paidOrders, Modifier.weight(1f))
                    }
                }
            }
            Row(Modifier.fillMaxWidth().padding(top = 22.dp).background(Color(0xFFE9EBE3), RoundedCornerShape(8.dp)).padding(4.dp)) {
                listOf("Reservations" to vm.reservations.size, "Purchases" to vm.orders.size).forEachIndexed { index, value ->
                    TextButton(onClick = { activityTab = index }, colors = ButtonDefaults.textButtonColors(contentColor = if (activityTab == index) Color(0xFF171817) else Color(0xFF5C6259)), modifier = Modifier.weight(1f).background(if (activityTab == index) Color.White else Color.Transparent, RoundedCornerShape(5.dp))) {
                        Text("${value.first}  ${value.second}", fontWeight = FontWeight.Bold, fontSize = 13.sp)
                    }
                }
            }
            Text(if (activityTab == 0) "Reservation history" else "Purchase history", fontSize = 20.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(top = 24.dp, bottom = 11.dp))
        }
        if (activityTab == 0) {
            if (vm.reservations.isEmpty()) item(key = "empty-reservations") { HistoryEmpty("No reservations yet.", "Start a reservation from the menu or Reserve page.") }
            items(vm.reservations, key = { "reservation-${it.id}" }) { reservation ->
                ActivityCard(title = reservation.reference, kind = "Reservation", status = reservation.status, details = listOf("SCHEDULE" to reservation.reservation_at, "TYPE" to if (reservation.type == "table") "${reservation.table_size}-seater table" else "Exclusive venue", "TOTAL" to money(reservation.total_amount)), onClick = { onReservation(reservation.id) })
            }
        } else {
            if (vm.orders.isEmpty()) item(key = "empty-orders") { HistoryEmpty("No purchases yet.", "Your completed menu orders will appear here.") }
            items(vm.orders, key = { "order-${it.id}" }) { order ->
                ActivityCard(title = "Order #${order.id}", kind = "Purchase", status = order.payment_status, details = listOf("DATE" to (order.created_at ?: "—"), "PAYMENT" to order.payment_method.uppercase(), "TOTAL" to money(order.total)), onClick = { onOrder(order.id) })
            }
        }
    }
}

@Composable private fun HistoryMetric(label: String, value: Int, modifier: Modifier = Modifier) { Row(modifier.padding(14.dp), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) { Text(label, color = Color(0xFF687064), fontSize = 12.sp); Text(value.toString(), fontSize = 20.sp, fontWeight = FontWeight.Black) } }

@Composable
private fun ActivityCard(title: String, kind: String, status: String, details: List<Pair<String, String>>, onClick: () -> Unit) {
    Surface(Modifier.fillMaxWidth().padding(bottom = 10.dp).clickable(onClick = onClick), shape = RoundedCornerShape(8.dp), color = Color.White, border = androidx.compose.foundation.BorderStroke(1.dp, Color(0xFFD7DACF))) {
        Column(Modifier.padding(16.dp)) {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.Top) {
                Column { Text(kind, color = Color(0xFF73796F), fontSize = 12.sp, fontWeight = FontWeight.Bold); Text(title, fontSize = 17.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(top = 3.dp)) }
                val statusColor = when (status.lowercase()) { "paid", "confirmed" -> Color(0xFF257342); "cancelled" -> Color(0xFFB72C2C); "completed" -> Color(0xFF315EC9); else -> Color(0xFF5D6259) }
                val statusBackground = when (status.lowercase()) { "paid", "confirmed" -> Color(0xFFE5F4E9); "cancelled" -> Color(0xFFFDEAEA); "completed" -> Color(0xFFE9EEFB); else -> Color(0xFFEFF0EC) }
                Text(status.replaceFirstChar { it.uppercase() }, color = statusColor, fontSize = 11.sp, fontWeight = FontWeight.ExtraBold, modifier = Modifier.background(statusBackground, RoundedCornerShape(50)).padding(horizontal = 10.dp, vertical = 6.dp))
            }
            HorizontalDivider(Modifier.padding(vertical = 13.dp), color = Color(0xFFE3E5DD))
            details.forEach { (label, value) -> Row(Modifier.fillMaxWidth().padding(vertical = 3.dp), horizontalArrangement = Arrangement.SpaceBetween) { Text(label, color = Color(0xFF767C72), fontSize = 10.sp, fontWeight = FontWeight.Bold); Text(value, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, maxLines = 1) } }
            Text("View details", color = Color(0xFF626B00), fontSize = 12.sp, fontWeight = FontWeight.ExtraBold, modifier = Modifier.align(Alignment.End).padding(top = 10.dp))
        }
    }
}

@Composable private fun HistoryEmpty(title: String, message: String) { Surface(Modifier.fillMaxWidth(), shape = RoundedCornerShape(8.dp), color = Color.Transparent, border = androidx.compose.foundation.BorderStroke(1.dp, Color(0xFFCBD0C3))) { Column(Modifier.fillMaxWidth().padding(34.dp), horizontalAlignment = Alignment.CenterHorizontally) { Text(title, fontWeight = FontWeight.Bold); Text(message, color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 13.sp, modifier = Modifier.padding(top = 7.dp)) } } }
@Composable private fun DetailDialog(title: String, summary: String, lines: List<String>, close: () -> Unit) { AlertDialog(onDismissRequest = close, confirmButton = { TextButton(onClick = close) { Text("Close") } }, title = { Text(title) }, text = { Column { Text(summary, fontWeight = FontWeight.Bold); lines.forEach { Text(it, modifier = Modifier.padding(top = 8.dp)) } } }) }
@Composable private fun ReservationScreen(vm: AppViewModel, onDetail: (Int) -> Unit, setMessage: (String) -> Unit) {
    var type by remember { mutableStateOf("table") }; var phone by remember { mutableStateOf(vm.user?.phone.orEmpty()) }; var date by remember { mutableStateOf("") }; var size by remember { mutableStateOf("4") }; var guests by remember { mutableStateOf("20") }; var notes by remember { mutableStateOf("") }; var foodRequest by remember { mutableStateOf("") }; var payment by remember { mutableStateOf("cash") }; var reference by remember { mutableStateOf("") }; var proofUri by remember { mutableStateOf<Uri?>(null) }; var menuItems by remember { mutableStateOf<Map<Int, Int>>(emptyMap()) }
    val context = androidx.compose.ui.platform.LocalContext.current; val calendar = remember { Calendar.getInstance() }; val dateFormat = remember { SimpleDateFormat("yyyy-MM-dd HH:mm", Locale.US) }; val proofPicker = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri -> proofUri = uri }
    val selectedFoodTotal = menuItems.mapNotNull { entry -> vm.products.find { it.id == entry.key }?.price?.times(entry.value) }.sum()
    val reservationFee = if (type == "table") mapOf("1" to 100.0, "2" to 150.0, "4" to 250.0, "8" to 450.0, "12" to 650.0)[size] ?: 250.0 else 5000.0
    val canPay = payment == "cash" || (reference.length == 13 && proofUri != null)
    Text("BOOK A RESERVATION", color = Color(0xFF777F00), fontSize = 11.sp, letterSpacing = 1.5.sp, fontWeight = FontWeight.ExtraBold)
    Text("Plan your visit", fontSize = 34.sp, lineHeight = 38.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(top = 6.dp))
    Text("Complete the details below. We will confirm your request after review.", color = Color(0xFF70766D), fontSize = 14.sp, lineHeight = 20.sp, modifier = Modifier.padding(top = 5.dp)); Spacer(Modifier.height(18.dp))
    Row(Modifier.horizontalScroll(rememberScrollState())) { FilterChip(selected = type == "table", onClick = { type = "table" }, label = { Text("Table") }, modifier = Modifier.padding(end = 8.dp)); FilterChip(selected = type == "exclusive", onClick = { type = "exclusive" }, label = { Text("Exclusive venue") }) }
    Spacer(Modifier.height(10.dp)); OutlinedTextField(phone, { phone = it.filter(Char::isDigit).take(11) }, label = { Text("Phone (09XXXXXXXXX)") }, singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number), modifier = Modifier.fillMaxWidth()); Spacer(Modifier.height(10.dp))
    DateTimePickerField(date, "Choose your schedule", "Select date and time", "Tap to choose a date, then choose a time.") { showDateTimePicker(context, calendar, dateFormat) { date = it } }; Spacer(Modifier.height(10.dp))
    if (type == "table") {
        Text("Table size", color = MaterialTheme.colorScheme.onSurfaceVariant); Row(Modifier.horizontalScroll(rememberScrollState())) { listOf("1", "2", "4", "8", "12").forEach { value -> FilterChip(selected = size == value, onClick = { size = value }, label = { Text("$value seats") }, modifier = Modifier.padding(end = 6.dp)) } }
    } else {
        OutlinedTextField(guests, { guests = it.filter(Char::isDigit).take(3) }, label = { Text("Number of guests") }, singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number), modifier = Modifier.fillMaxWidth())
    }
    Spacer(Modifier.height(12.dp)); Text("Food request", fontWeight = FontWeight.Bold); Text("Optional pre-order items", color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 12.sp)
    vm.products.take(8).forEach { product ->
        Row(Modifier.fillMaxWidth().padding(top = 8.dp), verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) { Text(product.name, fontWeight = FontWeight.SemiBold); Text(money(product.price), color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 12.sp) }
            val quantity = menuItems[product.id] ?: 0
            IconButton(onClick = { menuItems = if (quantity <= 1) menuItems - product.id else menuItems + (product.id to quantity - 1) }, enabled = quantity > 0) { Icon(Icons.Default.Remove, "Remove") }
            Text(quantity.toString(), fontWeight = FontWeight.Bold)
            IconButton(onClick = { if (quantity < 22) menuItems = menuItems + (product.id to quantity + 1) }) { Icon(Icons.Default.Add, "Add") }
        }
    }
    OutlinedTextField(foodRequest, { foodRequest = it.take(2000) }, label = { Text("Food instructions") }, minLines = 2, modifier = Modifier.fillMaxWidth().padding(top = 8.dp))
    OutlinedTextField(notes, { notes = it.take(2000) }, label = { Text("Additional notes") }, minLines = 2, modifier = Modifier.fillMaxWidth().padding(top = 8.dp))
    Spacer(Modifier.height(12.dp)); Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) { Text("Estimated total", fontWeight = FontWeight.Bold); Text(money(reservationFee + selectedFoodTotal), fontWeight = FontWeight.Bold) }
    Spacer(Modifier.height(8.dp)); Row(verticalAlignment = Alignment.CenterVertically) { Text("Payment:"); Spacer(Modifier.width(8.dp)); FilterChip(selected = payment == "cash", onClick = { payment = "cash" }, label = { Text("Cash") }); Spacer(Modifier.width(6.dp)); FilterChip(selected = payment == "gcash", onClick = { payment = "gcash" }, label = { Text("GCash") }) }
    if (payment == "gcash") { vm.gcashQrUrl?.let { AsyncImage(it, "GCash QR code", Modifier.fillMaxWidth().height(140.dp).padding(vertical = 8.dp), contentScale = ContentScale.Inside) }; OutlinedTextField(reference, { reference = it.filter(Char::isDigit).take(13) }, label = { Text("13-digit GCash reference") }, singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number), modifier = Modifier.fillMaxWidth()); Spacer(Modifier.height(8.dp)); OutlinedButton(onClick = { proofPicker.launch("image/*") }, modifier = Modifier.fillMaxWidth()) { Text(proofUri?.lastPathSegment ?: "Attach payment proof") } }
    Spacer(Modifier.height(16.dp)); Button(onClick = { vm.placeReservation(context, type, phone, date, size, guests, notes, foodRequest, menuItems, payment, reference, proofUri) { ok -> if (ok) { menuItems = emptyMap(); setMessage("Reservation request submitted.") } else setMessage("Reservation could not be submitted.") } }, enabled = !vm.busy && phone.matches(Regex("09\\d{9}")) && date.isNotBlank() && (type == "table" || (guests.toIntOrNull() ?: 0) in 1..300) && canPay, modifier = Modifier.fillMaxWidth()) { Text(if (vm.busy) "Submitting..." else "Request reservation") }
    Spacer(Modifier.height(26.dp)); Text("Recent reservations", style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold); Spacer(Modifier.height(14.dp))
    if (vm.reservations.isEmpty()) Text("Nothing here yet.", color = MaterialTheme.colorScheme.onSurfaceVariant) else vm.reservations.forEach { reservation ->
        ListItem(headlineContent = { Text("#${reservation.id}  ${reservation.reference}  ${reservation.status}  ${money(reservation.total_amount)}") }, modifier = Modifier.fillMaxWidth().padding(bottom = 6.dp).clickable { onDetail(reservation.id) })
    }
}
private fun money(value: Double) = "₱${String.format(Locale.US, "%,.2f", value)}"

private fun Uri.toMultipart(context: Context, fieldName: String): MultipartBody.Part? {
    val type = context.contentResolver.getType(this) ?: "image/jpeg"
    val extension = type.substringAfter('/', "jpg")
    val bytes = context.contentResolver.openInputStream(this)?.use { it.readBytes() } ?: return null
    val body = bytes.toRequestBody(type.toMediaTypeOrNull())

    return MultipartBody.Part.createFormData(fieldName, "$fieldName.$extension", body)
}
