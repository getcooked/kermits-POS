package com.getcooked.kermits

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.background
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
    var cart by mutableStateOf<Map<Int, Int>>(emptyMap()); private set
    var busy by mutableStateOf(false); private set
    var error by mutableStateOf<String?>(null); private set
    val signedIn get() = store.token != null
    init { if (signedIn) refresh() }
    fun login(login: String, password: String) = run { busy = true; viewModelScope.launch { try { val result = api.login(LoginRequest(login, password)); store.token = result.data.token; user = result.data.user; load() } catch (e: Exception) { error = e.message ?: "Unable to sign in" } finally { busy = false } } }
    fun logout() = viewModelScope.launch { runCatching { api.logout() }; store.clear(); user = null; products = emptyList(); orders = emptyList(); reservations = emptyList() }
    fun refresh() = viewModelScope.launch { busy = true; try { load() } catch (e: Exception) { error = "Could not load the latest menu" } finally { busy = false } }
    private suspend fun load() { val catalog = api.products().data; products = catalog.products; orders = api.orders().data; reservations = api.reservations().data; user = user ?: api.me()["data"] }
    fun add(product: Product) { val count = (cart[product.id] ?: 0) + 1; if (count <= product.stock) cart = cart + (product.id to count) }
    fun remove(product: Product) { val count = (cart[product.id] ?: 0) - 1; cart = if (count > 0) cart + (product.id to count) else cart - product.id }
    fun placeOrder(payment: String, done: (Boolean) -> Unit) = viewModelScope.launch { busy = true; try { val response = api.createOrder(mapOf("items" to cart.map { mapOf("product_id" to it.key, "quantity" to it.value) }, "payment_method" to payment)); check(response.isSuccessful); cart = emptyMap(); orders = api.orders().data; done(true) } catch (e: Exception) { error = "Order could not be placed"; done(false) } finally { busy = false } }
    fun placeReservation(phone: String, at: String, size: String, done: (Boolean) -> Unit) = viewModelScope.launch { busy = true; try { val response = api.createReservation("table".formPart(), size.formPart(), phone.formPart(), at.formPart(), null, null, "cash".formPart(), null); check(response.isSuccessful); reservations = api.reservations().data; done(true) } catch (e: Exception) { error = "Reservation could not be submitted"; done(false) } finally { busy = false } }
    companion object {
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
    var tab by remember { mutableIntStateOf(0) }
    var payment by remember { mutableStateOf("cash") }
    var orderMessage by remember { mutableStateOf<String?>(null) }
    if (!vm.signedIn) {
        Column(Modifier.fillMaxSize().background(Color(0xFF171817)).padding(28.dp), verticalArrangement = Arrangement.Center) {
            BrandLogo(Modifier.size(88.dp).background(Color.White, androidx.compose.foundation.shape.CircleShape).padding(5.dp))
            Spacer(Modifier.height(22.dp)); Text("KERMIT'S", style = MaterialTheme.typography.labelLarge, color = Color(0xFFB5C019), letterSpacing = 2.sp)
            Text("Good food.\nGood company.", style = MaterialTheme.typography.displaySmall, color = Color.White, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(12.dp)); Text("Sign in to order your favorites and plan your next visit.", color = Color(0xFFB7BAB5))
            Spacer(Modifier.height(28.dp))
            OutlinedTextField(login, { login = it }, label = { Text("Username or email") }, singleLine = true, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(10.dp)); OutlinedTextField(password, { password = it }, label = { Text("Password") }, singleLine = true, visualTransformation = PasswordVisualTransformation(), modifier = Modifier.fillMaxWidth())
            vm.error?.let { Text(it, color = MaterialTheme.colorScheme.error, modifier = Modifier.padding(top = 10.dp)) }
            Spacer(Modifier.height(18.dp)); Button(onClick = { vm.login(login, password) }, enabled = !vm.busy && login.isNotBlank() && password.isNotBlank(), modifier = Modifier.fillMaxWidth()) { Text(if (vm.busy) "Signing in..." else "Sign in") }
        }
        return
    }
    Scaffold(containerColor = Color(0xFFF0F0F0), bottomBar = { NavigationBar(containerColor = Color(0xFF202124)) { listOf("Menu", "Orders", "Reservations", "Account").forEachIndexed { index, label -> NavigationBarItem(selected = tab == index, onClick = { tab = index }, colors = NavigationBarItemDefaults.colors(selectedIconColor = Color(0xFF202124), selectedTextColor = Color.White, indicatorColor = Color(0xFFB5C019), unselectedIconColor = Color(0xFFB7BAB5), unselectedTextColor = Color(0xFFB7BAB5)), icon = { Icon(listOf(Icons.Default.Home, Icons.Default.ReceiptLong, Icons.Default.CalendarMonth, Icons.Default.Person)[index], label) }, label = { Text(label) }) } } }) { padding ->
        Column(Modifier.padding(padding).fillMaxSize().verticalScroll(rememberScrollState()).padding(18.dp)) {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) { Row(verticalAlignment = Alignment.CenterVertically) { BrandLogo(Modifier.size(42.dp).background(Color.White, androidx.compose.foundation.shape.CircleShape).padding(2.dp)); Spacer(Modifier.width(10.dp)); Text("Hi, ${vm.user?.name?.substringBefore(' ') ?: "there"}", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold) }; if (vm.busy) CircularProgressIndicator(Modifier.size(22.dp), strokeWidth = 2.dp) }
            Spacer(Modifier.height(18.dp))
            when (tab) {
                0 -> MenuScreen(vm, payment, { payment = it }, { message -> orderMessage = message })
                1 -> HistoryScreen("Your orders", vm.orders.map { "#${it.id}  ${money(it.total)}  ${it.payment_status}" })
                2 -> ReservationScreen(vm) { message -> orderMessage = message }
                else -> { Text(vm.user?.email.orEmpty(), color = MaterialTheme.colorScheme.onSurfaceVariant); Spacer(Modifier.height(22.dp)); OutlinedButton(onClick = { vm.logout() }) { Text("Sign out") } }
            }
            orderMessage?.let { Text(it, color = MaterialTheme.colorScheme.primary, modifier = Modifier.padding(top = 12.dp)) }
            vm.error?.let { Text(it, color = MaterialTheme.colorScheme.error, modifier = Modifier.padding(top = 8.dp)) }
        }
    }
}

@Composable
private fun MenuScreen(vm: AppViewModel, payment: String, setPayment: (String) -> Unit, setMessage: (String) -> Unit) {
    var query by remember { mutableStateOf("") }
    val filtered = vm.products.filter { query.isBlank() || it.name.contains(query, ignoreCase = true) }
    Text("Today's menu", style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold)
    Text("Prepared fresh for every guest.", color = MaterialTheme.colorScheme.onSurfaceVariant)
    Spacer(Modifier.height(14.dp)); OutlinedTextField(query, { query = it }, placeholder = { Text("Search menu") }, leadingIcon = { Icon(Icons.Default.Search, null) }, singleLine = true, modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp))
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

@Composable private fun HistoryScreen(title: String, rows: List<String>) { Text(title, style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold); Spacer(Modifier.height(14.dp)); if (rows.isEmpty()) Text("Nothing here yet.", color = MaterialTheme.colorScheme.onSurfaceVariant) else rows.forEach { row -> ListItem(headlineContent = { Text(row) }, modifier = Modifier.fillMaxWidth().padding(bottom = 6.dp)) } }
@Composable private fun ReservationScreen(vm: AppViewModel, setMessage: (String) -> Unit) {
    var phone by remember { mutableStateOf(vm.user?.phone.orEmpty()) }; var date by remember { mutableStateOf("") }; var size by remember { mutableStateOf("4") }
    Text("Plan your visit", style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold); Text("Table reservations are reviewed by our team.", color = MaterialTheme.colorScheme.onSurfaceVariant); Spacer(Modifier.height(16.dp))
    OutlinedTextField(phone, { phone = it }, label = { Text("Phone (09XXXXXXXXX)") }, singleLine = true, modifier = Modifier.fillMaxWidth()); Spacer(Modifier.height(10.dp))
    OutlinedTextField(date, { date = it }, label = { Text("Date and time (YYYY-MM-DD HH:MM)") }, singleLine = true, modifier = Modifier.fillMaxWidth()); Spacer(Modifier.height(10.dp))
    Text("Table size", color = MaterialTheme.colorScheme.onSurfaceVariant); Row { listOf("1", "2", "4", "8", "12").forEach { value -> FilterChip(selected = size == value, onClick = { size = value }, label = { Text(value) }, modifier = Modifier.padding(end = 6.dp)) } }
    Spacer(Modifier.height(16.dp)); Button(onClick = { vm.placeReservation(phone, date, size) { ok -> setMessage(if (ok) "Reservation request submitted." else "Reservation could not be submitted.") } }, enabled = !vm.busy && phone.matches(Regex("09\\d{9}")) && date.isNotBlank(), modifier = Modifier.fillMaxWidth()) { Text("Request reservation") }
    Spacer(Modifier.height(26.dp)); HistoryScreen("Recent reservations", vm.reservations.map { "${it.reference}  ${it.status}  ${money(it.total_amount)}" })
}
private fun money(value: Double) = "₱${String.format(Locale.US, "%,.2f", value)}"
