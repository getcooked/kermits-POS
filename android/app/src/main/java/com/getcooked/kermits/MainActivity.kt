package com.getcooked.kermits

import android.app.DatePickerDialog
import android.app.TimePickerDialog

import android.content.Context
import android.net.Uri
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.compose.setContent
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.ReceiptLong
import androidx.compose.material.icons.filled.Remove
import androidx.compose.material.icons.filled.Search
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.sp
import androidx.compose.ui.unit.dp
import coil.compose.AsyncImage
import androidx.compose.runtime.*
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
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
import java.util.Locale
import java.text.SimpleDateFormat
import java.util.Calendar

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        val store = SessionStore(this)
        val log = HttpLoggingInterceptor().apply { level = HttpLoggingInterceptor.Level.BASIC }
        val clientBuilder = OkHttpClient.Builder().addInterceptor { chain ->
            chain.proceed(chain.request().newBuilder().apply { store.token?.let { header("Authorization", "Bearer $it") }; header("Accept", "application/json") }.build())
        }
        if (BuildConfig.DEBUG) clientBuilder.addInterceptor(log)
        val client = clientBuilder.build()
        val api = Retrofit.Builder().baseUrl(BuildConfig.API_BASE_URL).client(client).addConverterFactory(MoshiConverterFactory.create()).build().create(KermitsApi::class.java)
        setContent { KermitsTheme { KermitsApp(ViewModelProvider(this, AppViewModel.factory(api, store))[AppViewModel::class.java]) } }
    }
}

private const val BRAND_LOGO_URL = "https://kermits-pos.com/kermits-logo.jpg"

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
    val signedIn get() = store.token != null
    init { if (signedIn) refresh() }
    fun login(login: String, password: String) {
        if (busy || login.isBlank() || password.isBlank()) return
        busy = true
        error = null
        viewModelScope.launch {
            try {
                val response = api.login(LoginRequest(login.trim(), password))
                if (!response.isSuccessful) {
                    error = apiError(response.errorBody()?.string()) ?: "The username/email or password is incorrect. The mobile app accepts customer accounts only."
                    return@launch
                }
                val result = response.body()?.data
                if (result == null) {
                    error = "Kermit's returned an incomplete sign-in response. Please try again."
                    return@launch
                }
                store.token = result.token
                user = result.user
                try {
                    load()
                } catch (_: Exception) {
                    error = "Signed in, but the latest menu could not be loaded. Pull down to refresh when you are online."
                }
            } catch (_: Exception) {
                error = "Unable to reach Kermit's. Check your internet connection and try again."
            } finally {
                busy = false
            }
        }
    }
    fun logout() = viewModelScope.launch { runCatching { api.logout() }; store.clear(); user = null; products = emptyList(); orders = emptyList(); reservations = emptyList() }
    fun refresh() = viewModelScope.launch {
        busy = true
        error = null
        try {
            // Validate a persisted token first. Previously an expired token kept the
            // app on its signed-in screen, making the login form inaccessible.
            user = api.me()["data"] ?: throw IllegalStateException("Missing account data")
            val catalog = api.products().data
            products = catalog.products
            gcashQrUrl = catalog.gcash_qr_url
            runCatching { orders = api.orders().data }
            runCatching { reservations = api.reservations().data }
        } catch (exception: HttpException) {
            if (exception.code() == 401) {
                store.clear()
                user = null
                products = emptyList()
                orders = emptyList()
                reservations = emptyList()
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
    private suspend fun load() { val catalog = api.products().data; products = catalog.products; gcashQrUrl = catalog.gcash_qr_url; runCatching { orders = api.orders().data }; runCatching { reservations = api.reservations().data }; runCatching { user = user ?: api.me()["data"] } }
    fun sendCode(email: String, done: (String?) -> Unit) = viewModelScope.launch { busy = true; error = null; try { val response = api.sendRegistrationCode(SendCodeRequest(email.trim().lowercase())); check(response.isSuccessful); registrationMessage = "Verification code sent to ${email.trim()}"; done(response.body()?.data?.challenge) } catch (_: Exception) { error = "Could not send the verification code"; done(null) } finally { busy = false } }
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

@Composable fun KermitsTheme(content: @Composable () -> Unit) { MaterialTheme(colorScheme = lightColorScheme(primary = Color(0xFF737D00), onPrimary = Color.White, background = Color(0xFFF0F0F0), surface = Color.White, onSurface = Color(0xFF202124)), typography = Typography().copy(headlineLarge = Typography().headlineLarge.copy(fontWeight = FontWeight.Bold), headlineMedium = Typography().headlineMedium.copy(fontWeight = FontWeight.Bold)), content = content) }

@Composable
private fun BrandLogo(modifier: Modifier = Modifier) {
    AsyncImage(BRAND_LOGO_URL, "Kermit's logo", modifier.clip(androidx.compose.foundation.shape.CircleShape), contentScale = ContentScale.Crop)
}

@Composable
fun KermitsApp(vm: AppViewModel) {
    var login by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var registering by remember { mutableStateOf(false) }
    var tab by remember { mutableIntStateOf(0) }
    var payment by remember { mutableStateOf("cash") }
    var orderMessage by remember { mutableStateOf<String?>(null) }
    var selectedOrder by remember { mutableStateOf<Order?>(null) }
    var selectedReservation by remember { mutableStateOf<Reservation?>(null) }
    if (!vm.signedIn) {
        if (registering) RegistrationScreen(vm, onBack = { registering = false }) else LoginScreen(vm, login, { login = it }, password, { password = it }, onRegister = { registering = true })
        return
    }
    Scaffold(containerColor = Color(0xFFF0F0F0), bottomBar = { NavigationBar(containerColor = Color(0xFF202124)) { listOf("Menu", "Orders", "Reservations", "Account").forEachIndexed { index, label -> NavigationBarItem(selected = tab == index, onClick = { tab = index }, colors = NavigationBarItemDefaults.colors(selectedIconColor = Color(0xFF202124), selectedTextColor = Color.White, indicatorColor = Color(0xFFB5C019), unselectedIconColor = Color(0xFFB7BAB5), unselectedTextColor = Color(0xFFB7BAB5)), icon = { Icon(listOf(Icons.Default.Home, Icons.Default.ReceiptLong, Icons.Default.CalendarMonth, Icons.Default.Person)[index], label) }, label = { Text(label) }) } } }) { padding ->
        Column(Modifier.padding(padding).fillMaxSize().verticalScroll(rememberScrollState()).padding(18.dp)) {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) { Row(verticalAlignment = Alignment.CenterVertically) { BrandLogo(Modifier.size(42.dp).background(Color.White, androidx.compose.foundation.shape.CircleShape).padding(2.dp)); Spacer(Modifier.width(10.dp)); Text("Hi, ${vm.user?.name?.substringBefore(' ') ?: "there"}", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold) }; if (vm.busy) CircularProgressIndicator(Modifier.size(22.dp), strokeWidth = 2.dp) }
            Spacer(Modifier.height(18.dp))
            when (tab) {
                0 -> MenuScreen(vm, payment, { payment = it }, { message -> orderMessage = message }, onReserve = { tab = 2 })
                1 -> HistoryScreen("Your orders", vm.orders.map { "#${it.id}  ${money(it.total)}  ${it.payment_status}" }, onClick = { vm.loadOrder(it) { selectedOrder = it } })
                2 -> ReservationScreen(vm, onDetail = { id -> vm.loadReservation(id) { selectedReservation = it } }) { message -> orderMessage = message }
                else -> { Text(vm.user?.email.orEmpty(), color = MaterialTheme.colorScheme.onSurfaceVariant); Spacer(Modifier.height(22.dp)); OutlinedButton(onClick = { vm.logout() }) { Text("Sign out") } }
            }
            orderMessage?.let { Text(it, color = MaterialTheme.colorScheme.primary, modifier = Modifier.padding(top = 12.dp)) }
            vm.error?.let { Text(it, color = MaterialTheme.colorScheme.error, modifier = Modifier.padding(top = 8.dp)) }
        }
    }
    selectedOrder?.let { order -> DetailDialog("Order #${order.id}", "${money(order.total)} · ${order.payment_status}", order.items.map { item -> "${item.quantity} × ${item.name}  ${money(item.subtotal)}" } + listOf("Payment: ${order.payment_method}${order.payment_reference?.let { " · Ref $it" } ?: ""}") + (order.reservation?.let { reservation -> listOf("Table: ${reservation.table_size}-seater", "Schedule: ${reservation.reservation_at}", "Reservation fee: ${money(reservation.total_amount)}") } ?: emptyList())) { selectedOrder = null } }
    selectedReservation?.let { reservation -> DetailDialog("Reservation ${reservation.reference}", "${reservation.status} · ${money(reservation.total_amount)}", listOf("${reservation.type} · ${reservation.guests ?: reservation.table_size} guest(s)", reservation.reservation_at, "Payment: ${reservation.payment_method} · ${reservation.payment_status}${reservation.payment_reference?.let { " · Ref $it" } ?: ""}", "Reservation fee: ${money(reservation.reservation_fee)}", "Food total: ${money(reservation.food_total)}") + reservation.items.map { item -> "${item.quantity} × ${item.name}  ${money(item.subtotal)}" }) { selectedReservation = null } }
}

@Composable
private fun LoginScreen(vm: AppViewModel, login: String, setLogin: (String) -> Unit, password: String, setPassword: (String) -> Unit, onRegister: () -> Unit) {
    BoxWithConstraints(Modifier.fillMaxSize().background(Color(0xFFF5F5EF))) {
        val wide = maxWidth >= 600.dp
        if (wide) Row(Modifier.fillMaxSize()) {
            BrandPanel(Modifier.weight(0.96f).fillMaxHeight())
            LoginForm(vm, login, setLogin, password, setPassword, onRegister, Modifier.weight(1.04f).fillMaxHeight())
        } else Column(Modifier.fillMaxSize()) {
            BrandPanel(Modifier.fillMaxWidth().heightIn(min = 205.dp, max = 270.dp))
            LoginForm(vm, login, setLogin, password, setPassword, onRegister, Modifier.fillMaxWidth().weight(1f))
        }
    }
}

@Composable
private fun BrandPanel(modifier: Modifier) {
    Column(modifier.background(Brush.linearGradient(listOf(Color(0xFF131413), Color(0xFF1C1E1A), Color(0xFF30332B)))).padding(horizontal = 28.dp, vertical = 30.dp)) {
        BrandLogo(Modifier.size(84.dp).background(Color.White, androidx.compose.foundation.shape.CircleShape).padding(6.dp))
        Column(Modifier.weight(1f), verticalArrangement = Arrangement.Center) {
            Text("RESTAURANT POS", color = Color(0xFFAAB514), fontSize = 12.sp, letterSpacing = 1.8.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(10.dp))
            Text("Simple tools for\nbetter service.", color = Color.White, fontSize = 34.sp, lineHeight = 37.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(14.dp))
            Text("Manage sales, products, inventory, reports, and receipts from one reliable system.", color = Color(0xFFB9BCB5), fontSize = 15.sp, lineHeight = 23.sp)
        }
        Text("Time-honored recipes since 2000", color = Color(0xFF858982), fontSize = 12.sp)
    }
}

@Composable
private fun LoginForm(vm: AppViewModel, login: String, setLogin: (String) -> Unit, password: String, setPassword: (String) -> Unit, onRegister: () -> Unit, modifier: Modifier) {
    val canLogIn = !vm.busy && login.isNotBlank() && password.isNotBlank()
    val submitLogin = { if (canLogIn) vm.login(login, password) }
    Column(modifier.background(Color(0xFFF7F7F1)).verticalScroll(rememberScrollState()).padding(horizontal = 26.dp, vertical = 34.dp), verticalArrangement = Arrangement.Center) {
        Column(Modifier.fillMaxWidth().widthIn(max = 520.dp).align(Alignment.CenterHorizontally)) {
            Text("WELCOME BACK", color = Color(0xFFAAB514), fontSize = 12.sp, letterSpacing = 1.8.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(8.dp)); Text("Log in to your account", color = Color(0xFF202124), fontSize = 30.sp, lineHeight = 35.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(7.dp)); Text("Enter your details to continue to Kermit’s.", color = Color(0xFF687286), fontSize = 15.sp)
            Spacer(Modifier.height(28.dp))
            OutlinedTextField(login, setLogin, label = { Text("Username or email address") }, placeholder = { Text("Username or name@gmail.com") }, singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email, imeAction = ImeAction.Next), colors = loginFieldColors(), shape = RoundedCornerShape(13.dp), modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(16.dp)); OutlinedTextField(password, setPassword, label = { Text("Password") }, placeholder = { Text("Enter your password") }, singleLine = true, visualTransformation = PasswordVisualTransformation(), keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password, imeAction = ImeAction.Done), keyboardActions = KeyboardActions(onDone = { submitLogin() }), colors = loginFieldColors(), shape = RoundedCornerShape(13.dp), modifier = Modifier.fillMaxWidth())
            vm.error?.let { Text(it, color = MaterialTheme.colorScheme.error, fontSize = 13.sp, modifier = Modifier.padding(top = 12.dp)) }
            Spacer(Modifier.height(18.dp)); Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) { Text("Your sign-in stays secure on this device", color = Color(0xFF687286), fontSize = 13.sp); Text("Customer app", color = Color(0xFF626B00), fontSize = 13.sp, fontWeight = FontWeight.Bold) }
            Spacer(Modifier.height(15.dp)); Button(onClick = { vm.login(login, password) }, enabled = !vm.busy && login.isNotBlank() && password.isNotBlank(), shape = RoundedCornerShape(13.dp), colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF171817), contentColor = Color.White), modifier = Modifier.fillMaxWidth().height(56.dp)) { Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) { Text(if (vm.busy) "Signing in..." else "Log in", fontWeight = FontWeight.Bold, fontSize = 16.sp); Text("→", fontSize = 22.sp) } }
            Spacer(Modifier.height(18.dp)); Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.Center) { Text("New customer? ", color = Color(0xFF687286), fontSize = 12.sp); TextButton(onClick = onRegister, contentPadding = PaddingValues(0.dp)) { Text("Create an account", color = Color(0xFF626B00), fontSize = 12.sp, fontWeight = FontWeight.Bold) } }
        }
    }
}

@Composable
private fun RegistrationScreen(vm: AppViewModel, onBack: () -> Unit) {
    var email by remember { mutableStateOf("") }; var challenge by remember { mutableStateOf<String?>(null) }; var code by remember { mutableStateOf("") }; var token by remember { mutableStateOf<String?>(null) }
    var name by remember { mutableStateOf("") }; var username by remember { mutableStateOf("") }; var phone by remember { mutableStateOf("") }; var password by remember { mutableStateOf("") }; var confirmation by remember { mutableStateOf("") }
    Column(Modifier.fillMaxSize().background(Color(0xFFF7F7F1)).verticalScroll(rememberScrollState()).padding(24.dp)) {
        TextButton(onClick = onBack, contentPadding = PaddingValues(0.dp)) { Text("← Back to log in", color = Color(0xFF626B00), fontWeight = FontWeight.Bold) }
        Spacer(Modifier.height(12.dp)); Text("SIGN UP", color = Color(0xFFAAB514), fontSize = 12.sp, letterSpacing = 1.8.sp, fontWeight = FontWeight.Bold); Text("Create your account", fontSize = 30.sp, fontWeight = FontWeight.Bold); Text("Verify your Gmail first, then create your customer account securely.", color = Color(0xFF687286), modifier = Modifier.padding(top = 7.dp))
        Spacer(Modifier.height(22.dp)); Text("Step 1  Gmail verification", fontWeight = FontWeight.Bold); Text("Use a Gmail address you can open now.", color = Color(0xFF687286), fontSize = 12.sp, modifier = Modifier.padding(top = 3.dp)); Spacer(Modifier.height(10.dp))
        OutlinedTextField(email, { email = it }, label = { Text("Gmail address") }, placeholder = { Text("name@gmail.com") }, enabled = challenge == null, singleLine = true, colors = loginFieldColors(), modifier = Modifier.fillMaxWidth())
        Spacer(Modifier.height(8.dp)); Button(onClick = { vm.sendCode(email) { issuedChallenge -> challenge = issuedChallenge } }, enabled = challenge == null && email.matches(Regex("^[^@\\s]+@gmail\\.com$")) && !vm.busy, modifier = Modifier.fillMaxWidth()) { Text("Send code") }
        if (challenge != null && token == null) { Spacer(Modifier.height(12.dp)); OutlinedTextField(code, { code = it.take(6) }, label = { Text("6-digit verification code") }, singleLine = true, colors = loginFieldColors(), modifier = Modifier.fillMaxWidth()); Spacer(Modifier.height(8.dp)); OutlinedButton(onClick = { vm.verifyCode(challenge!!, email, code) { verified -> token = verified } }, enabled = code.length == 6 && !vm.busy, modifier = Modifier.fillMaxWidth()) { Text("Verify Gmail") } }
        if (token != null) { Spacer(Modifier.height(22.dp)); Text("Step 2  Account details", fontWeight = FontWeight.Bold); Spacer(Modifier.height(10.dp)); RegistrationField("Full name", name) { name = it }; RegistrationField("Username", username) { username = it }; RegistrationField("Phone number", phone) { phone = it }; RegistrationField("Password", password, true) { password = it }; RegistrationField("Confirm password", confirmation, true) { confirmation = it }; Spacer(Modifier.height(12.dp)); Button(onClick = { vm.register(RegisterRequest(token!!, name, username, email, phone, password, confirmation)) { ok -> if (ok) onBack() } }, enabled = !vm.busy && name.isNotBlank() && username.length >= 3 && phone.matches(Regex("09\\d{9}")) && password.length >= 12 && password == confirmation, modifier = Modifier.fillMaxWidth()) { Text("Create account") } }
        vm.error?.let { Text(it, color = MaterialTheme.colorScheme.error, fontSize = 13.sp, modifier = Modifier.padding(top = 12.dp)) }; vm.registrationMessage?.let { Text(it, color = MaterialTheme.colorScheme.primary, fontSize = 13.sp, modifier = Modifier.padding(top = 12.dp)) }
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
    val categories = listOf("All") + vm.products.mapNotNull { it.category }.distinct()
    val filtered = vm.products.filter { product ->
        (category == "All" || product.category == category) &&
            (query.isBlank() || product.name.contains(query, ignoreCase = true) || product.description.orEmpty().contains(query, ignoreCase = true))
    }
    val cartTotal = vm.cart.mapNotNull { entry -> vm.products.find { it.id == entry.key }?.price?.times(entry.value) }.sum()
    val canPay = payment == "cash" || (paymentReference.length == 13 && proofUri != null)
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) { Text("Today's menu", style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold); OutlinedButton(onClick = onReserve, shape = RoundedCornerShape(10.dp)) { Text("Reserve") } }
    Text("Prepared fresh for every guest.", color = MaterialTheme.colorScheme.onSurfaceVariant)
    Spacer(Modifier.height(14.dp)); OutlinedTextField(query, { query = it }, placeholder = { Text("Search menu") }, leadingIcon = { Icon(Icons.Default.Search, null) }, singleLine = true, modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp))
    Spacer(Modifier.height(10.dp)); Row(Modifier.horizontalScroll(rememberScrollState())) { categories.forEach { value -> FilterChip(selected = category == value, onClick = { category = value }, label = { Text(value) }, modifier = Modifier.padding(end = 8.dp)) } }
    Spacer(Modifier.height(8.dp))
    filtered.groupBy { it.category ?: "Favorites" }.forEach { (category, items) ->
        Text(category, style = MaterialTheme.typography.titleMedium, color = MaterialTheme.colorScheme.primary, modifier = Modifier.padding(vertical = 8.dp))
        items.forEach { product ->
            ElevatedCard(Modifier.fillMaxWidth().padding(bottom = 10.dp), shape = RoundedCornerShape(12.dp)) { Column {
                if (product.image_url != null) AsyncImage(product.image_url, product.name, Modifier.fillMaxWidth().height(142.dp).clip(RoundedCornerShape(topStart = 12.dp, topEnd = 12.dp)), contentScale = ContentScale.Crop) else Box(Modifier.fillMaxWidth().height(142.dp).background(MaterialTheme.colorScheme.primaryContainer), contentAlignment = Alignment.Center) { Text(product.name.take(1), style = MaterialTheme.typography.headlineMedium) }
                Row(Modifier.padding(13.dp), verticalAlignment = Alignment.CenterVertically) { Column(Modifier.weight(1f)) { Text(product.name, fontWeight = FontWeight.Bold); Text(product.description.orEmpty(), maxLines = 2, color = MaterialTheme.colorScheme.onSurfaceVariant); Text("${money(product.price)} · ${product.stock} available", fontWeight = FontWeight.Bold, modifier = Modifier.padding(top = 4.dp)) }; Row(verticalAlignment = Alignment.CenterVertically) { val quantity = vm.cart[product.id] ?: 0; IconButton(onClick = { vm.remove(product) }, enabled = quantity > 0) { Icon(Icons.Default.Remove, "Remove") }; Text(quantity.toString(), fontWeight = FontWeight.Bold); FilledIconButton(onClick = { vm.add(product) }, enabled = quantity < product.stock, colors = IconButtonDefaults.filledIconButtonColors(containerColor = Color(0xFF202124))) { Icon(Icons.Default.Add, "Add") } } }
            } }
        }
    }
    if (vm.cart.isNotEmpty()) {
        Surface(Modifier.fillMaxWidth().padding(top = 8.dp), shape = RoundedCornerShape(14.dp), color = Color(0xFF202124), contentColor = Color.White) {
            Column(Modifier.padding(16.dp)) {
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) { Text("${vm.cart.values.sum()} item(s) in your order", fontWeight = FontWeight.Bold); Text(money(cartTotal)) }
                if (!checkingOut) {
                    Spacer(Modifier.height(10.dp))
                    Button(onClick = { checkingOut = true }, modifier = Modifier.fillMaxWidth(), colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFB5C019), contentColor = Color(0xFF202124))) { Text("Continue to checkout") }
                } else {
                    Spacer(Modifier.height(12.dp))
                    Text("Table schedule", fontWeight = FontWeight.Bold)
                    OutlinedTextField(phone, { phone = it.filter(Char::isDigit).take(11) }, label = { Text("Phone") }, singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number), modifier = Modifier.fillMaxWidth(), colors = OutlinedTextFieldDefaults.colors(focusedTextColor = Color.White, unfocusedTextColor = Color.White, focusedBorderColor = Color(0xFFB5C019), unfocusedBorderColor = Color(0xFF777A73)))
                    Spacer(Modifier.height(8.dp))
                    OutlinedTextField(date, {}, label = { Text("Date and time") }, placeholder = { Text("Choose schedule") }, readOnly = true, singleLine = true, modifier = Modifier.fillMaxWidth().clickable { DatePickerDialog(context, { _, year, month, day -> calendar.set(year, month, day); TimePickerDialog(context, { _, hour, minute -> calendar.set(Calendar.HOUR_OF_DAY, hour); calendar.set(Calendar.MINUTE, minute); date = dateFormat.format(calendar.time) }, calendar.get(Calendar.HOUR_OF_DAY), calendar.get(Calendar.MINUTE), false).show() }, calendar.get(Calendar.YEAR), calendar.get(Calendar.MONTH), calendar.get(Calendar.DAY_OF_MONTH)).show() }, colors = OutlinedTextFieldDefaults.colors(focusedTextColor = Color.White, unfocusedTextColor = Color.White, focusedBorderColor = Color(0xFFB5C019), unfocusedBorderColor = Color(0xFF777A73)))
                    Spacer(Modifier.height(8.dp))
                    Row(Modifier.horizontalScroll(rememberScrollState())) { listOf("1", "2", "4", "8", "12").forEach { value -> FilterChip(selected = tableSize == value, onClick = { tableSize = value }, label = { Text("$value seats") }, modifier = Modifier.padding(end = 6.dp)) } }
                    Spacer(Modifier.height(8.dp))
                    OutlinedTextField(notes, { notes = it.take(2000) }, label = { Text("Notes") }, minLines = 2, modifier = Modifier.fillMaxWidth(), colors = OutlinedTextFieldDefaults.colors(focusedTextColor = Color.White, unfocusedTextColor = Color.White, focusedBorderColor = Color(0xFFB5C019), unfocusedBorderColor = Color(0xFF777A73)))
                    Spacer(Modifier.height(10.dp))
                    Row(verticalAlignment = Alignment.CenterVertically) { Text("Payment:"); Spacer(Modifier.width(8.dp)); FilterChip(selected = payment == "cash", onClick = { setPayment("cash") }, label = { Text("Cash") }); Spacer(Modifier.width(6.dp)); FilterChip(selected = payment == "gcash", onClick = { setPayment("gcash") }, label = { Text("GCash") }) }
                    if (payment == "gcash") {
                        vm.gcashQrUrl?.let { AsyncImage(it, "GCash QR code", Modifier.fillMaxWidth().height(150.dp).padding(vertical = 8.dp), contentScale = ContentScale.Inside) }
                        OutlinedTextField(paymentReference, { paymentReference = it.filter(Char::isDigit).take(13) }, label = { Text("13-digit GCash reference") }, singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number), modifier = Modifier.fillMaxWidth(), colors = OutlinedTextFieldDefaults.colors(focusedTextColor = Color.White, unfocusedTextColor = Color.White, focusedBorderColor = Color(0xFFB5C019), unfocusedBorderColor = Color(0xFF777A73)))
                        Spacer(Modifier.height(8.dp))
                        OutlinedButton(onClick = { proofPicker.launch("image/*") }, modifier = Modifier.fillMaxWidth()) { Text(proofUri?.lastPathSegment ?: "Attach payment proof") }
                    }
                    Spacer(Modifier.height(12.dp))
                    Button(onClick = { vm.placeOrder(context, CheckoutDetails(phone, date, tableSize, payment, paymentReference, notes, proofUri)) { ok -> setMessage(if (ok) "Order and table request submitted." else "Order could not be submitted.") } }, enabled = !vm.busy && phone.matches(Regex("09\\d{9}")) && date.isNotBlank() && canPay, modifier = Modifier.fillMaxWidth(), colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFB5C019), contentColor = Color(0xFF202124))) { Text(if (vm.busy) "Submitting..." else "Submit order") }
                }
            }
        }
    }
}

@Composable private fun HistoryScreen(title: String, rows: List<String>, onClick: ((Int) -> Unit)? = null) { Text(title, style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold); Spacer(Modifier.height(14.dp)); if (rows.isEmpty()) Text("Nothing here yet.", color = MaterialTheme.colorScheme.onSurfaceVariant) else rows.forEach { row -> val id = Regex("#?(\\d+)").find(row)?.groupValues?.get(1)?.toIntOrNull(); ListItem(headlineContent = { Text(row) }, modifier = Modifier.fillMaxWidth().padding(bottom = 6.dp).clickable { if (id != null) onClick?.invoke(id) }) } }
@Composable private fun DetailDialog(title: String, summary: String, lines: List<String>, close: () -> Unit) { AlertDialog(onDismissRequest = close, confirmButton = { TextButton(onClick = close) { Text("Close") } }, title = { Text(title) }, text = { Column { Text(summary, fontWeight = FontWeight.Bold); lines.forEach { Text(it, modifier = Modifier.padding(top = 8.dp)) } } }) }
@Composable private fun ReservationScreen(vm: AppViewModel, onDetail: (Int) -> Unit, setMessage: (String) -> Unit) {
    var type by remember { mutableStateOf("table") }; var phone by remember { mutableStateOf(vm.user?.phone.orEmpty()) }; var date by remember { mutableStateOf("") }; var size by remember { mutableStateOf("4") }; var guests by remember { mutableStateOf("20") }; var notes by remember { mutableStateOf("") }; var foodRequest by remember { mutableStateOf("") }; var payment by remember { mutableStateOf("cash") }; var reference by remember { mutableStateOf("") }; var proofUri by remember { mutableStateOf<Uri?>(null) }; var menuItems by remember { mutableStateOf<Map<Int, Int>>(emptyMap()) }
    val context = androidx.compose.ui.platform.LocalContext.current; val calendar = remember { Calendar.getInstance() }; val dateFormat = remember { SimpleDateFormat("yyyy-MM-dd HH:mm", Locale.US) }; val proofPicker = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri -> proofUri = uri }
    val selectedFoodTotal = menuItems.mapNotNull { entry -> vm.products.find { it.id == entry.key }?.price?.times(entry.value) }.sum()
    val reservationFee = if (type == "table") mapOf("1" to 100.0, "2" to 150.0, "4" to 250.0, "8" to 450.0, "12" to 650.0)[size] ?: 250.0 else 5000.0
    val canPay = payment == "cash" || (reference.length == 13 && proofUri != null)
    Text("Plan your visit", style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold); Text("Reservations are reviewed by our team.", color = MaterialTheme.colorScheme.onSurfaceVariant); Spacer(Modifier.height(16.dp))
    Row(Modifier.horizontalScroll(rememberScrollState())) { FilterChip(selected = type == "table", onClick = { type = "table" }, label = { Text("Table") }, modifier = Modifier.padding(end = 8.dp)); FilterChip(selected = type == "exclusive", onClick = { type = "exclusive" }, label = { Text("Exclusive venue") }) }
    Spacer(Modifier.height(10.dp)); OutlinedTextField(phone, { phone = it.filter(Char::isDigit).take(11) }, label = { Text("Phone (09XXXXXXXXX)") }, singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number), modifier = Modifier.fillMaxWidth()); Spacer(Modifier.height(10.dp))
    OutlinedTextField(date, {}, label = { Text("Preferred date and time") }, placeholder = { Text("Choose your schedule") }, readOnly = true, singleLine = true, modifier = Modifier.fillMaxWidth().clickable { DatePickerDialog(context, { _, year, month, day -> calendar.set(year, month, day); TimePickerDialog(context, { _, hour, minute -> calendar.set(Calendar.HOUR_OF_DAY, hour); calendar.set(Calendar.MINUTE, minute); date = dateFormat.format(calendar.time) }, calendar.get(Calendar.HOUR_OF_DAY), calendar.get(Calendar.MINUTE), false).show() }, calendar.get(Calendar.YEAR), calendar.get(Calendar.MONTH), calendar.get(Calendar.DAY_OF_MONTH)).show() }); Spacer(Modifier.height(10.dp))
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
    Spacer(Modifier.height(26.dp)); HistoryScreen("Recent reservations", vm.reservations.map { "#${it.id}  ${it.reference}  ${it.status}  ${money(it.total_amount)}" }, onDetail)
}
private fun money(value: Double) = "₱${String.format(Locale.US, "%,.2f", value)}"

private fun Uri.toMultipart(context: Context, fieldName: String): MultipartBody.Part? {
    val type = context.contentResolver.getType(this) ?: "image/jpeg"
    val extension = type.substringAfter('/', "jpg")
    val bytes = context.contentResolver.openInputStream(this)?.use { it.readBytes() } ?: return null
    val body = bytes.toRequestBody(type.toMediaTypeOrNull())

    return MultipartBody.Part.createFormData(fieldName, "$fieldName.$extension", body)
}
