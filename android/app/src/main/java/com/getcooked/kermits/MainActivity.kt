package com.getcooked.kermits

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.ReceiptLong
import androidx.compose.material.icons.filled.Remove
import androidx.compose.material.icons.filled.Search
import androidx.compose.material.icons.filled.ShoppingBag
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.sp
import androidx.compose.ui.unit.dp
import coil.compose.AsyncImage
import androidx.compose.runtime.*
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.launch
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.moshi.MoshiConverterFactory
import com.squareup.moshi.Moshi
import java.util.Locale

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
    fun login(login: String, password: String) = run { busy = true; error = null; viewModelScope.launch { try { val response = api.login(LoginRequest(login.trim(), password)); if (!response.isSuccessful) { error = apiError(response.errorBody()?.string()) ?: "The username/email or password is incorrect."; return@launch }; val result = response.body()?.data ?: error("Empty login response"); store.token = result.token; user = result.user; try { load() } catch (_: Exception) { error = "Signed in, but the latest menu could not be loaded." } } catch (_: Exception) { error = "Unable to reach Kermit's. Check your internet connection." } finally { busy = false } } }
    fun logout() = viewModelScope.launch { runCatching { api.logout() }; store.clear(); user = null; products = emptyList(); orders = emptyList(); reservations = emptyList() }
    fun refresh() = viewModelScope.launch { busy = true; error = null; try { val catalog = api.products().data; products = catalog.products; gcashQrUrl = catalog.gcash_qr_url } catch (_: Exception) { error = "Could not load the latest menu" }; runCatching { orders = api.orders().data }; runCatching { reservations = api.reservations().data }; runCatching { user = user ?: api.me()["data"] }; busy = false }
    private suspend fun load() { val catalog = api.products().data; products = catalog.products; gcashQrUrl = catalog.gcash_qr_url; runCatching { orders = api.orders().data }; runCatching { reservations = api.reservations().data }; runCatching { user = user ?: api.me()["data"] } }
    fun sendCode(email: String, done: (String?) -> Unit) = viewModelScope.launch { busy = true; error = null; try { val response = api.sendRegistrationCode(SendCodeRequest(email.trim().lowercase())); check(response.isSuccessful); registrationMessage = "Verification code sent to ${email.trim()}"; done(response.body()?.data?.challenge) } catch (_: Exception) { error = "Could not send the verification code"; done(null) } finally { busy = false } }
    fun verifyCode(challenge: String, email: String, code: String, done: (String?) -> Unit) = viewModelScope.launch { busy = true; error = null; try { val response = api.verifyRegistrationCode(VerifyCodeRequest(challenge, email.trim().lowercase(), code)); check(response.isSuccessful); done(response.body()?.data?.registration_token) } catch (_: Exception) { error = "The verification code is invalid or expired"; done(null) } finally { busy = false } }
    fun register(request: RegisterRequest, done: (Boolean) -> Unit) = viewModelScope.launch { busy = true; error = null; try { val response = api.register(request); check(response.isSuccessful); registrationMessage = "Account created. You can now log in."; done(true) } catch (_: Exception) { error = "Could not create the account. Check your details."; done(false) } finally { busy = false } }
    fun loadOrder(id: Int, done: (Order?) -> Unit) = viewModelScope.launch { busy = true; try { done(api.order(id).body()?.get("data")) } catch (_: Exception) { error = "Could not load this order"; done(null) } finally { busy = false } }
    fun loadReservation(id: Int, done: (Reservation?) -> Unit) = viewModelScope.launch { busy = true; try { done(api.reservation(id).body()?.get("data")) } catch (_: Exception) { error = "Could not load this reservation"; done(null) } finally { busy = false } }
    fun add(product: Product) { val count = (cart[product.id] ?: 0) + 1; if (count <= product.stock) cart = cart + (product.id to count) }
    fun remove(product: Product) { val count = (cart[product.id] ?: 0) - 1; cart = if (count > 0) cart + (product.id to count) else cart - product.id }
    fun placeOrder(payment: String, done: (Boolean) -> Unit) = viewModelScope.launch { busy = true; try { val response = api.createOrder(mapOf("items" to cart.map { mapOf("product_id" to it.key, "quantity" to it.value) }, "payment_method" to payment)); check(response.isSuccessful); cart = emptyMap(); orders = api.orders().data; done(true) } catch (e: Exception) { error = "Order could not be placed"; done(false) } finally { busy = false } }
    fun placeReservation(phone: String, at: String, size: String, done: (Boolean) -> Unit) = viewModelScope.launch { busy = true; try { val response = api.createReservation("table".formPart(), size.formPart(), phone.formPart(), at.formPart(), null, null, "cash".formPart(), null); check(response.isSuccessful); reservations = api.reservations().data; done(true) } catch (e: Exception) { error = "Reservation could not be submitted"; done(false) } finally { busy = false } }
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
    selectedOrder?.let { DetailDialog("Order #${it.id}", "${money(it.total)} · ${it.payment_status}", it.items.map { item -> "${item.quantity} × ${item.name}  ${money(item.subtotal)}" }) { selectedOrder = null } }
    selectedReservation?.let { reservation -> DetailDialog("Reservation ${reservation.reference}", "${reservation.status} · ${money(reservation.total_amount)}", listOf("${reservation.type} · ${reservation.guests ?: reservation.table_size} guest(s)", reservation.reservation_at, "Payment: ${reservation.payment_method}") + reservation.items.map { item -> "${item.quantity} × ${item.name}  ${money(item.subtotal)}" }) { selectedReservation = null } }
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
    Column(modifier.background(Color(0xFF171817)).padding(horizontal = 28.dp, vertical = 30.dp)) {
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
    Column(modifier.background(Color(0xFFF7F7F1)).verticalScroll(rememberScrollState()).padding(horizontal = 26.dp, vertical = 34.dp), verticalArrangement = Arrangement.Center) {
        Column(Modifier.fillMaxWidth().widthIn(max = 520.dp).align(Alignment.CenterHorizontally)) {
            Text("WELCOME BACK", color = Color(0xFFAAB514), fontSize = 12.sp, letterSpacing = 1.8.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(8.dp)); Text("Log in to your account", color = Color(0xFF202124), fontSize = 30.sp, lineHeight = 35.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(7.dp)); Text("Enter your details to continue to Kermit’s.", color = Color(0xFF687286), fontSize = 15.sp)
            Spacer(Modifier.height(28.dp))
            OutlinedTextField(login, setLogin, label = { Text("Username or email address") }, placeholder = { Text("Username or name@gmail.com") }, singleLine = true, colors = loginFieldColors(), shape = RoundedCornerShape(13.dp), modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(16.dp)); OutlinedTextField(password, setPassword, label = { Text("Password") }, placeholder = { Text("Enter your password") }, singleLine = true, visualTransformation = PasswordVisualTransformation(), colors = loginFieldColors(), shape = RoundedCornerShape(13.dp), modifier = Modifier.fillMaxWidth())
            vm.error?.let { Text(it, color = MaterialTheme.colorScheme.error, fontSize = 13.sp, modifier = Modifier.padding(top = 12.dp)) }
            Spacer(Modifier.height(18.dp)); Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) { Row(verticalAlignment = Alignment.CenterVertically) { Checkbox(checked = false, onCheckedChange = null); Text("Keep me signed in", color = Color(0xFF687286), fontSize = 13.sp) }; Text("Forgot password?", color = Color(0xFF626B00), fontSize = 13.sp, fontWeight = FontWeight.Bold) }
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
    val filtered = vm.products.filter { query.isBlank() || it.name.contains(query, ignoreCase = true) }
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) { Text("Today's menu", style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold); OutlinedButton(onClick = onReserve, shape = RoundedCornerShape(10.dp)) { Text("Reserve") } }
    Text("Prepared fresh for every guest.", color = MaterialTheme.colorScheme.onSurfaceVariant)
    Spacer(Modifier.height(14.dp)); OutlinedTextField(query, { query = it }, placeholder = { Text("Search menu") }, leadingIcon = { Icon(Icons.Default.Search, null) }, singleLine = true, modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp))
    vm.gcashQrUrl?.let { AsyncImage(it, "GCash QR code", Modifier.fillMaxWidth().height(120.dp).padding(vertical = 8.dp), contentScale = ContentScale.Inside) }
    Spacer(Modifier.height(10.dp)); Row(Modifier.horizontalScroll(rememberScrollState())) { listOf("All") + vm.products.mapNotNull { it.category }.distinct().forEach { category -> FilterChip(selected = if (category == "All") query.isBlank() else query == category, onClick = { query = if (category == "All") "" else category }, label = { Text(category) }, modifier = Modifier.padding(end = 8.dp)) } }
    Spacer(Modifier.height(8.dp))
    filtered.groupBy { it.category ?: "Favorites" }.forEach { (category, items) ->
        Text(category, style = MaterialTheme.typography.titleMedium, color = MaterialTheme.colorScheme.primary, modifier = Modifier.padding(vertical = 8.dp))
        items.forEach { product ->
            ElevatedCard(Modifier.fillMaxWidth().padding(bottom = 10.dp), shape = RoundedCornerShape(12.dp)) { Column {
                if (product.image_url != null) AsyncImage(product.image_url, product.name, Modifier.fillMaxWidth().height(142.dp).clip(RoundedCornerShape(topStart = 12.dp, topEnd = 12.dp)), contentScale = ContentScale.Crop) else Box(Modifier.fillMaxWidth().height(142.dp).background(MaterialTheme.colorScheme.primaryContainer), contentAlignment = Alignment.Center) { Text(product.name.take(1), style = MaterialTheme.typography.headlineMedium) }
                Row(Modifier.padding(13.dp), verticalAlignment = Alignment.CenterVertically) { Column(Modifier.weight(1f)) { Text(product.name, fontWeight = FontWeight.Bold); Text(product.description.orEmpty(), maxLines = 2, color = MaterialTheme.colorScheme.onSurfaceVariant); Text(money(product.price), fontWeight = FontWeight.Bold, modifier = Modifier.padding(top = 4.dp)) }; FilledIconButton(onClick = { vm.add(product) }, colors = IconButtonDefaults.filledIconButtonColors(containerColor = Color(0xFF202124))) { Icon(Icons.Default.Add, "Add") } }
            } }
        }
    }
    if (vm.cart.isNotEmpty()) { Surface(Modifier.fillMaxWidth().padding(top = 8.dp), shape = RoundedCornerShape(14.dp), color = Color(0xFF202124), contentColor = Color.White) { Column(Modifier.padding(16.dp)) { Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) { Text("${vm.cart.values.sum()} item(s) in your order", fontWeight = FontWeight.Bold); Text(money(vm.cart.mapNotNull { entry -> vm.products.find { it.id == entry.key }?.price?.times(entry.value) }.sum())) }; Row(verticalAlignment = Alignment.CenterVertically) { Text("Payment:"); Spacer(Modifier.width(8.dp)); FilterChip(selected = payment == "cash", onClick = { setPayment("cash") }, label = { Text("Cash") }); Spacer(Modifier.width(6.dp)); FilterChip(selected = payment == "gcash", onClick = { setPayment("gcash") }, label = { Text("GCash") }) }; Button(onClick = { vm.placeOrder(payment) { ok -> setMessage(if (ok) "Order placed successfully." else "Order could not be placed.") } }, modifier = Modifier.fillMaxWidth(), colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFB5C019), contentColor = Color(0xFF202124))) { Text("Place order") } } } }
}

@Composable private fun HistoryScreen(title: String, rows: List<String>, onClick: ((Int) -> Unit)? = null) { Text(title, style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold); Spacer(Modifier.height(14.dp)); if (rows.isEmpty()) Text("Nothing here yet.", color = MaterialTheme.colorScheme.onSurfaceVariant) else rows.forEach { row -> val id = Regex("#?(\\d+)").find(row)?.groupValues?.get(1)?.toIntOrNull(); ListItem(headlineContent = { Text(row) }, modifier = Modifier.fillMaxWidth().padding(bottom = 6.dp).clickable { if (id != null) onClick?.invoke(id) }) } }
@Composable private fun DetailDialog(title: String, summary: String, lines: List<String>, close: () -> Unit) { AlertDialog(onDismissRequest = close, confirmButton = { TextButton(onClick = close) { Text("Close") } }, title = { Text(title) }, text = { Column { Text(summary, fontWeight = FontWeight.Bold); lines.forEach { Text(it, modifier = Modifier.padding(top = 8.dp)) } } }) }
@Composable private fun ReservationScreen(vm: AppViewModel, onDetail: (Int) -> Unit, setMessage: (String) -> Unit) {
    var phone by remember { mutableStateOf(vm.user?.phone.orEmpty()) }; var date by remember { mutableStateOf("") }; var size by remember { mutableStateOf("4") }
    Text("Plan your visit", style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold); Text("Table reservations are reviewed by our team.", color = MaterialTheme.colorScheme.onSurfaceVariant); Spacer(Modifier.height(16.dp))
    OutlinedTextField(phone, { phone = it }, label = { Text("Phone (09XXXXXXXXX)") }, singleLine = true, modifier = Modifier.fillMaxWidth()); Spacer(Modifier.height(10.dp))
    OutlinedTextField(date, { date = it }, label = { Text("Date and time (YYYY-MM-DD HH:MM)") }, singleLine = true, modifier = Modifier.fillMaxWidth()); Spacer(Modifier.height(10.dp))
    Text("Table size", color = MaterialTheme.colorScheme.onSurfaceVariant); Row { listOf("1", "2", "4", "8", "12").forEach { value -> FilterChip(selected = size == value, onClick = { size = value }, label = { Text(value) }, modifier = Modifier.padding(end = 6.dp)) } }
    Spacer(Modifier.height(16.dp)); Button(onClick = { vm.placeReservation(phone, date, size) { ok -> setMessage(if (ok) "Reservation request submitted." else "Reservation could not be submitted.") } }, enabled = !vm.busy && phone.matches(Regex("09\\d{9}")) && date.isNotBlank(), modifier = Modifier.fillMaxWidth()) { Text("Request reservation") }
    Spacer(Modifier.height(26.dp)); HistoryScreen("Recent reservations", vm.reservations.map { "#${it.id}  ${it.reference}  ${it.status}  ${money(it.total_amount)}" }, onDetail)
}
private fun money(value: Double) = "₱${String.format(Locale.US, "%,.2f", value)}"
