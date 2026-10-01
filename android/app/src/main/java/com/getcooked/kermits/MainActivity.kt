package com.getcooked.kermits

import android.Manifest
import android.annotation.SuppressLint
import android.app.DatePickerDialog
import android.app.TimePickerDialog

import android.content.Context
import android.content.ContextWrapper
import android.content.Intent
import android.content.pm.PackageManager
import android.location.Address
import android.location.Geocoder
import android.location.Location
import android.location.LocationListener
import android.location.LocationManager
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.CancellationSignal
import android.os.Looper
import android.os.SystemClock
import android.print.PrintAttributes
import android.print.PrintJob
import android.print.PrintManager
import android.view.ViewGroup
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.activity.compose.BackHandler
import androidx.activity.ComponentActivity
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.compose.setContent
import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.animateContentSize
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.expandVertically
import androidx.compose.animation.shrinkVertically
import androidx.compose.animation.slideInHorizontally
import androidx.compose.animation.slideOutHorizontally
import androidx.compose.animation.togetherWith
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.tween
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.animation.slideInVertically
import androidx.compose.animation.slideOutVertically
import androidx.compose.foundation.gestures.snapping.rememberSnapFlingBehavior
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.GridItemSpan
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.ui.semantics.selected
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.*
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.Logout
import androidx.compose.material.icons.automirrored.filled.ReceiptLong
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material.icons.filled.Notifications
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.Remove
import androidx.compose.material.icons.filled.Search
import androidx.compose.material.icons.filled.ShoppingCart
import androidx.compose.material.icons.filled.Visibility
import androidx.compose.material.icons.filled.VisibilityOff
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.layout.onGloballyPositioned
import androidx.compose.ui.layout.positionInParent
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.clearAndSetSemantics
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.sp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import coil.compose.AsyncImage
import coil.request.ImageRequest
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.compose.LifecycleResumeEffect
import androidx.lifecycle.viewModelScope
import androidx.core.content.ContextCompat
import kotlinx.coroutines.async
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.coroutineScope
import kotlinx.coroutines.delay
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.launch
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlinx.coroutines.withContext
import kotlinx.coroutines.withTimeoutOrNull
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.toRequestBody
import retrofit2.HttpException
import com.squareup.moshi.Moshi
import com.squareup.moshi.JsonDataException
import com.squareup.moshi.JsonEncodingException
import java.io.IOException
import java.net.SocketTimeoutException
import java.time.OffsetDateTime
import java.time.LocalDate
import java.time.Period
import java.time.format.DateTimeFormatter
import java.util.Locale
import java.text.SimpleDateFormat
import java.util.Calendar
import kotlin.coroutines.resume

class MainActivity : ComponentActivity() {
    private var reservationUpdateId by mutableStateOf<Int?>(null)
    private var orderUpdateId by mutableStateOf<Int?>(null)

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        reservationUpdateId = intent.reservationUpdateId()
        orderUpdateId = intent.orderUpdateId()
        val store = SessionStore(this)
        val api = ApiClient.create(store)
        PushNotifications.createChannel(this)
        setContent {
            KermitsTheme {
                KermitsApp(
                    ViewModelProvider(this, AppViewModel.factory(api, store))[AppViewModel::class.java],
                    store,
                    reservationUpdateId,
                    onReservationUpdateConsumed = { reservationUpdateId = null },
                    orderUpdateId,
                    onOrderUpdateConsumed = { orderUpdateId = null },
                )
            }
        }
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        reservationUpdateId = intent.reservationUpdateId()
        orderUpdateId = intent.orderUpdateId()
    }
}

private fun Intent?.reservationUpdateId(): Int? = this
    ?.takeIf { it.action == "com.getcooked.kermits.OPEN_RESERVATION_UPDATE" }
    ?.getIntExtra("reservation_id", -1)
    ?.takeIf { it > 0 }

private fun Intent?.orderUpdateId(): Int? = when {
    this?.action == "com.getcooked.kermits.OPEN_ORDER_UPDATE" -> getIntExtra("order_id", -1)
    this?.action == Intent.ACTION_VIEW && data?.scheme == "kermits" && data?.host == "paymongo-return" ->
        data?.getQueryParameter("order")?.toIntOrNull()
    else -> null
}?.takeIf { it > 0 }

private val BRAND_LOGO_URL = BuildConfig.API_BASE_URL.substringBefore("/api/").trimEnd('/') + "/kermits-logo.jpg"
private val MOBILE_RECAPTCHA_URL = BuildConfig.API_BASE_URL.substringBefore("/api/").trimEnd('/') + "/mobile/recaptcha"

data class CheckoutDetails(
    val phone: String,
    val reservationAt: String,
    val tableSize: String,
    val paymentMethod: String,
    val paymentReference: String?,
    val notes: String,
    val proofUri: Uri?,
    val diningTableId: Int? = null,
)

class AppViewModel(private val api: KermitsApi, private val store: SessionStore) : ViewModel() {
    var user by mutableStateOf<User?>(null); private set
    var products by mutableStateOf<List<Product>>(emptyList()); private set
    var orders by mutableStateOf<List<Order>>(emptyList()); private set
    var reservations by mutableStateOf<List<Reservation>>(emptyList()); private set
    var gcashQrUrl by mutableStateOf<String?>(null); private set
    var payMongoEnabled by mutableStateOf(false); private set
    var pendingPayMongoOrderId by mutableStateOf<Int?>(null); private set
    var tableFees by mutableStateOf<Map<String, Double>>(emptyMap()); private set
    var diningTables by mutableStateOf<List<DiningTableOption>>(emptyList()); private set
    val tableSizes: List<String> get() =tableFees.keys.filter { it.toIntOrNull() != null }.sortedBy { it.toInt() }.ifEmpty { listOf("1", "2", "4", "8", "12") }
    var exclusiveFee by mutableDoubleStateOf(0.0); private set
    var exclusiveDownpaymentPercent by mutableIntStateOf(50); private set
    var cart by mutableStateOf<Map<Int, Int>>(emptyMap()); private set
    var readOrderNotificationKeys by mutableStateOf<Set<String>>(emptySet()); private set
    var busy by mutableStateOf(false); private set
    var refreshing by mutableStateOf(false); private set
    /** Bumped after every successful reload so screens holding their own server data (such as time slots) fetch it again. */
    var refreshCount by mutableIntStateOf(0); private set
    var error by mutableStateOf<String?>(null); private set
    var registrationMessage by mutableStateOf<String?>(null); private set
    var registrationNeedsVerification by mutableStateOf(false); private set
    private val mobileAuth = MobileAuth(api)
    var recaptchaUrl by mutableStateOf<String?>(null); private set
    private var recaptchaCompletion: ((String?) -> Unit)? = null
    var signedIn by mutableStateOf(store.token != null && store.keepsSession); private set
    var loginCooldownSeconds by mutableIntStateOf(0); private set
    var loginCooldownReason by mutableStateOf<String?>(null); private set
    private var loginCooldownDeadlineMillis = 0L
    private var loginCooldownJob: Job? = null
    fun clearError() { error = null }
    fun clearRegistrationFeedback() { error = null; registrationMessage = null; registrationNeedsVerification = false }
    suspend fun reservationSlots(date: String, type: String, guests: Int, table: Int? = null) = api.reservationSlots(date, type, guests, table).data
    init {
        if (signedIn) refresh() else store.clear()
    }
    fun login(login: String, password: String, keepSignedIn: Boolean) {
        if (busy || loginCooldownIsActive() || login.isBlank() || password.isBlank()) return
        withRecaptcha { token -> performLogin(login, password, keepSignedIn, token) }
    }
    private fun performLogin(login: String, password: String, keepSignedIn: Boolean, recaptchaToken: String?) {
        if (busy) return
        busy = true
        error = null
        viewModelScope.launch {
            try {
                val response = api.login(LoginRequest(login.trim(), password, recaptcha_token = recaptchaToken))
                if (!response.isSuccessful) {
                    val parsedError = parseApiError(response.errorBody()?.string())
                    when {
                        response.code() == 429 -> {
                            val retryAfter = parsedError?.retry_after?.takeIf { it > 0 }
                                ?: response.headers()["Retry-After"]?.trim()?.toIntOrNull()?.takeIf { it > 0 }
                                ?: DEFAULT_LOGIN_COOLDOWN_SECONDS
                            startLoginCooldown(retryAfter, apiErrorMessage(parsedError))
                        }
                        response.code() >= 500 -> error = "Kermit's server is temporarily unavailable. Please try again shortly."
                        else -> error = parsedError?.errors?.get("recaptcha_token")?.firstOrNull()
                            ?: apiErrorMessage(parsedError)
                            ?: "The email or password is incorrect. The mobile app accepts customer accounts only."
                    }
                    return@launch
                }
                val result = response.body()?.data
                if (result == null) {
                    error = "Kermit's returned an incomplete sign-in response. Please try again."
                    return@launch
                }
                store.saveSession(result.token, keepSignedIn, result.user.id)
                user = result.user
                readOrderNotificationKeys = store.orderNotificationReadKeys(result.user.id)
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
            } catch (exception: AuthRequestException) {
                error = exception.message
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
    private fun loginCooldownIsActive(): Boolean {
        if (loginCooldownDeadlineMillis <= 0L) return false
        if (SystemClock.elapsedRealtime() < loginCooldownDeadlineMillis) return true

        loginCooldownJob?.cancel()
        finishLoginCooldown()
        return false
    }
    private fun startLoginCooldown(seconds: Int, message: String?) {
        loginCooldownJob?.cancel()
        val durationSeconds = seconds.coerceAtLeast(1)
        loginCooldownDeadlineMillis = SystemClock.elapsedRealtime() + durationSeconds.toLong() * 1_000L
        loginCooldownSeconds = durationSeconds
        loginCooldownReason = message
            ?.replace(LOGIN_COOLDOWN_SUFFIX, "")
            ?.trim()
            ?.trimEnd('.')
            ?.takeIf { it.isNotBlank() }
            ?: "Too many login attempts"
        error = null
        loginCooldownJob = viewModelScope.launch {
            while (true) {
                val remainingMillis = loginCooldownDeadlineMillis - SystemClock.elapsedRealtime()
                if (remainingMillis <= 0L) break
                loginCooldownSeconds = ((remainingMillis + 999L) / 1_000L).toInt()
                delay(minOf(remainingMillis, 1_000L))
            }
            finishLoginCooldown()
        }
    }
    private fun finishLoginCooldown() {
        loginCooldownDeadlineMillis = 0L
        loginCooldownSeconds = 0
        loginCooldownReason = null
        loginCooldownJob = null
    }
    fun logout() = viewModelScope.launch {
        runCatching { api.deletePushInstallation() }
        runCatching { api.logout() }
        store.clear()
        user = null
        products = emptyList()
        orders = emptyList()
        reservations = emptyList()
        readOrderNotificationKeys = emptySet()
        signedIn = false
    }
    /**
     * [pulled] refreshes from a pull-down gesture, which shows its own spinner instead of blocking the screen.
     * A pull is never ignored while another request is busy: the indicator would snap back with nothing reloaded.
     */
    fun refresh(pulled: Boolean = false) = viewModelScope.launch {
        if (refreshing) return@launch
        if (pulled) refreshing = true else busy = true
        error = null
        try {
            // Validate a persisted token first. Previously an expired token kept the
            // app on its signed-in screen, making the login form inaccessible.
            user = api.me()["data"] ?: throw IllegalStateException("Missing account data")
            store.userId = user?.id
            readOrderNotificationKeys = user?.id?.let(store::orderNotificationReadKeys).orEmpty()
            load()
            refreshCount++
        } catch (exception: CancellationException) {
            throw exception
        } catch (exception: HttpException) {
            if (exception.code() == 401) {
                store.clear()
                user = null
                products = emptyList()
                orders = emptyList()
                reservations = emptyList()
                readOrderNotificationKeys = emptySet()
                signedIn = false
                error = "Your session has expired. Please log in again."
            } else {
                error = "Could not load the latest menu."
            }
        } catch (_: IOException) {
            error = "Could not load the latest menu. Check your internet connection."
        } catch (_: Exception) {
            // Parsing/adapter failures are app bugs, not connectivity problems.
            error = "Could not load the latest menu."
        } finally {
            if (pulled) refreshing = false else busy = false
        }
    }
    private suspend fun load() = coroutineScope {
        val catalogRequest = async { api.products().data }
        val ordersRequest = async { runCatching { api.orders().data }.getOrNull() }
        val reservationsRequest = async { runCatching { api.reservations().data }.getOrNull() }
        val catalog = catalogRequest.await()
        products = catalog.products
        gcashQrUrl = catalog.gcash_qr_url
        payMongoEnabled = catalog.paymongo_enabled
        tableFees = catalog.table_fees
        exclusiveFee = catalog.exclusive_fee
        exclusiveDownpaymentPercent = catalog.exclusive_downpayment_percent
        diningTables = catalog.tables.orEmpty()
        ordersRequest.await()?.let { orders = it }
        reservationsRequest.await()?.let { reservations = it }
    }
    fun sendCode(email: String, done: (String?) -> Unit) {
        if (busy) return
        registrationMessage = null
        runAuthRequest({
            val data = mobileAuth.sendCode(email)
            registrationMessage = "Verification code sent to ${data.email}. Check your inbox and spam folder."
            data.challenge
        }, done)
    }
    fun requestPasswordReset(email: String, done: (String?) -> Unit) {
        registrationMessage = null
        withRecaptcha { token ->
            runAuthRequest({
                val data = mobileAuth.requestPasswordReset(email, token)
                registrationMessage = "If ${data.email} belongs to a customer account, a 6-digit reset code was sent. Check your inbox and spam folder."
                data.challenge
            }, done)
        }
    }

    fun resetPassword(challenge: String, email: String, code: String, password: String, confirmation: String, done: (Boolean) -> Unit) =
        runAuthRequest({
            registrationMessage = mobileAuth.resetPassword(challenge, email, code, password, confirmation)
            true
        }, { done(it == true) })

    private fun withRecaptcha(done: (String?) -> Unit) {
        if (busy || recaptchaUrl != null) return
        busy = true
        error = null
        viewModelScope.launch {
            try {
                val enabled = api.recaptchaConfig().data.enabled
                busy = false
                if (enabled) {
                    recaptchaCompletion = done
                    recaptchaUrl = MOBILE_RECAPTCHA_URL
                } else {
                    done(null)
                }
            } catch (exception: CancellationException) {
                throw exception
            } catch (_: Exception) {
                error = "Could not load reCAPTCHA. Check your connection and try again."
                busy = false
            }
        }
    }

    fun completeRecaptcha(token: String) {
        val completion = recaptchaCompletion
        recaptchaCompletion = null
        recaptchaUrl = null
        if (token.isNotBlank()) completion?.invoke(token)
    }

    fun cancelRecaptcha() {
        recaptchaCompletion = null
        recaptchaUrl = null
    }

    fun verifyCode(challenge: String, email: String, code: String, done: (String?) -> Unit) =
        runAuthRequest({
            val data = mobileAuth.verifyCode(challenge, email, code)
            registrationMessage = "Gmail verified. Complete your account details below."
            data.registration_token
        }, done)

    fun register(request: RegisterRequest, done: (Boolean) -> Unit) =
        runAuthRequest({
            mobileAuth.register(request)
            registrationMessage = "Account created. You can now log in."
            true
        }, { done(it == true) })

    private fun <T> runAuthRequest(action: suspend () -> T, done: (T?) -> Unit) {
        if (busy) return
        busy = true
        error = null
        registrationNeedsVerification = false
        viewModelScope.launch {
            try {
                done(action())
            } catch (exception: CancellationException) {
                throw exception
            } catch (exception: AuthRequestException) {
                error = exception.message
                registrationNeedsVerification = exception.restartVerification
                if (exception.restartVerification) registrationMessage = null
                done(null)
            } catch (_: SocketTimeoutException) {
                error = "The server took too long to respond. Check your inbox before trying again."
                done(null)
            } catch (_: IOException) {
                error = "Could not contact Kermit's. Check your internet connection and try again."
                done(null)
            } catch (_: Exception) {
                error = "Could not read the server response. Please try again."
                done(null)
            } finally {
                busy = false
            }
        }
    }
    fun updateProfile(name: String, phone: String, address: String, done: (String?) -> Unit) = viewModelScope.launch {
        if (busy) return@launch
        busy = true
        error = null
        try {
            val response = api.updateProfile(UpdateProfileRequest(name.trim(), phone.trim(), address.trim()))
            if (!response.isSuccessful) {
                error = responseError(response, "Your personal information could not be updated.")
                done(null)
                return@launch
            }
            user = response.body()?.data ?: throw IllegalStateException("Missing account data")
            done(response.body()?.message ?: "Your personal information was updated.")
        } catch (_: Exception) {
            error = "Unable to update your account. Check your internet connection."
            done(null)
        } finally {
            busy = false
        }
    }
    fun sendPasswordVerificationCode(done: (String?) -> Unit) = viewModelScope.launch {
        if (busy) return@launch
        busy = true
        error = null
        try {
            val response = api.sendPasswordVerificationCode()
            if (!response.isSuccessful) {
                error = responseError(response, "The verification code could not be sent.")
                done(null)
                return@launch
            }
            done(response.body()?.message ?: "A verification code was sent to your email.")
        } catch (_: Exception) {
            error = "Unable to send the verification email. Check your internet connection."
            done(null)
        } finally {
            busy = false
        }
    }
    fun changePassword(code: String, newPassword: String, confirmation: String, done: (Boolean) -> Unit) = viewModelScope.launch {
        if (busy) return@launch
        busy = true
        error = null
        try {
            val response = api.updatePassword(ChangePasswordRequest(code.trim(), newPassword, confirmation))
            if (!response.isSuccessful) {
                error = responseError(response, "Your password could not be changed.")
                done(false)
                return@launch
            }
            registrationMessage = response.body()?.message ?: "Your password was changed. Log in with your new password."
            store.clear()
            user = null
            products = emptyList()
            orders = emptyList()
            reservations = emptyList()
            cart = emptyMap()
            readOrderNotificationKeys = emptySet()
            signedIn = false
            done(true)
        } catch (_: Exception) {
            error = "Unable to change your password. Check your internet connection."
            done(false)
        } finally {
            busy = false
        }
    }
    fun markOrderNotificationsRead(keys: Set<String>) {
        val customerId = user?.id ?: return
        readOrderNotificationKeys = readOrderNotificationKeys + keys
        store.saveOrderNotificationReadKeys(customerId, readOrderNotificationKeys)
    }
    fun loadOrder(id: Int, done: (Order?) -> Unit) = viewModelScope.launch {
        busy = true
        try {
            val order = api.order(id).body()?.get("data")
            if (order != null) orders = listOf(order) + orders.filterNot { it.id == order.id }
            done(order)
        } catch (_: Exception) {
            error = "Could not load this order"
            done(null)
        } finally {
            busy = false
        }
    }
    fun openPayMongo(context: Context, orderId: Int, knownCheckoutUrl: String? = null) = viewModelScope.launch {
        error = null
        val checkoutUrl = knownCheckoutUrl ?: run {
            busy = true
            try {
                val response = api.payMongoCheckout(orderId)
                if (!response.isSuccessful) {
                    error = responseError(response, "PayMongo checkout is unavailable right now. Please try again.")
                    return@launch
                }
                response.body()?.get("data")?.checkout_url
            } catch (exception: CancellationException) {
                throw exception
            } catch (_: Exception) {
                error = "Unable to reach Kermit's. Check your internet connection."
                return@launch
            } finally {
                busy = false
            }
        }
        val uri = checkoutUrl?.let(Uri::parse)
        if (uri == null || uri.scheme != "https" || uri.host != "checkout.paymongo.com") {
            error = "PayMongo checkout is unavailable right now. Please try again."
            return@launch
        }
        try {
            context.startActivity(Intent(Intent.ACTION_VIEW, uri))
            pendingPayMongoOrderId = orderId
        } catch (_: android.content.ActivityNotFoundException) {
            error = "Install or enable a web browser to pay with PayMongo."
        }
    }
    /** PayMongo confirms by webhook, which can land a few seconds after the customer is sent back to the app. */
    fun refreshPayMongoOrder(done: (Order) -> Unit) {
        val orderId = pendingPayMongoOrderId ?: return
        pendingPayMongoOrderId = null
        viewModelScope.launch {
            repeat(4) { attempt ->
                if (attempt > 0) delay(3_000)
                val order = runCatching { api.order(orderId).body()?.get("data") }.getOrNull() ?: return@repeat
                orders = listOf(order) + orders.filterNot { it.id == order.id }
                done(order)
                if (order.payment_status != "pending") {
                    runCatching { api.reservations().data }.getOrNull()?.let { reservations = it }
                    return@launch
                }
            }
        }
    }
    fun loadReservation(id: Int, done: (Reservation?) -> Unit) = viewModelScope.launch { busy = true; try { done(api.reservation(id).body()?.get("data")) } catch (_: Exception) { error = "Could not load this reservation"; done(null) } finally { busy = false } }
    fun add(product: Product) { val count = (cart[product.id] ?: 0) + 1; if (count <= product.stock) cart = cart + (product.id to count) }
    fun remove(product: Product) { val count = (cart[product.id] ?: 0) - 1; cart = if (count > 0) cart + (product.id to count) else cart - product.id }
    fun placeOrder(context: Context, details: CheckoutDetails, done: (Order?) -> Unit) = viewModelScope.launch {
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
            details.diningTableId?.let { parts["dining_table_id"] = it.toString().formPart() }
            cart.entries.forEachIndexed { index, entry ->
                parts["items[$index][product_id]"] = entry.key.toString().formPart()
                parts["items[$index][quantity]"] = entry.value.toString().formPart()
            }
            val proof = if (details.paymentMethod == "gcash") details.proofUri?.toMultipart(context, "payment_proof") else null
            val response = api.createOrder(parts, proof)
            if (!response.isSuccessful) {
                error = responseError(response, "Order details are invalid.")
                done(null)
                return@launch
            }
            val order = response.body()?.get("data")
            if (order == null) {
                cart = emptyMap()
                error = "The order was received, but its receipt could not be loaded. Check History before trying again."
                done(null)
                viewModelScope.launch {
                    runCatching { api.orders().data }.getOrNull()?.let { orders = it }
                    runCatching { api.reservations().data }.getOrNull()?.let { reservations = it }
                }
                return@launch
            }
            cart = emptyMap()
            orders = listOf(order) + orders.filterNot { it.id == order.id }
            order.reservation?.let { reservation ->
                reservations = listOf(reservation) + reservations.filterNot { it.id == reservation.id }
            }
            done(order)
            viewModelScope.launch {
                runCatching { api.orders().data }.getOrNull()?.let { orders = it }
                runCatching { api.reservations().data }.getOrNull()?.let { reservations = it }
            }
        } catch (_: Exception) {
            error = "Unable to reach Kermit's. Check your internet connection."
            done(null)
        } finally {
            busy = false
        }
    }
    fun placeReservation(context: Context, type: String, phone: String, at: String, size: String, guests: String, notes: String, foodRequest: String, menuItems: Map<Int, Int>, payment: String, reference: String, proofUri: Uri?, diningTableId: Int? = null, paymentPlan: String = "downpayment", done: (Boolean) -> Unit) = viewModelScope.launch {
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
                diningTableId = if (type == "table") diningTableId?.toString()?.formPart() else null,
                paymentPlan = if (type == "exclusive") paymentPlan.formPart() else null,
            )
            if (!response.isSuccessful) { error = responseError(response, "Reservation details are invalid"); done(false); return@launch }
            val created = response.body()?.get("data")
            reservations = api.reservations().data
            done(true)
            if (payment == "paymongo") created?.order_id?.let { openPayMongo(context, it, created.paymongo_checkout_url) }
        } catch (_: Exception) { error = "Unable to reach Kermit's. Check your internet connection."; done(false) } finally { busy = false }
    }
    companion object {
        private const val DEFAULT_LOGIN_COOLDOWN_SECONDS = 30
        private val LOGIN_COOLDOWN_SUFFIX = Regex("""\s*Try again in\s+\d+\s+seconds?\.?\s*$""", RegexOption.IGNORE_CASE)
        private val API_ERROR_ADAPTER = Moshi.Builder().build().adapter(ApiError::class.java)
        private fun parseApiError(body: String?): ApiError? = body?.let { runCatching { API_ERROR_ADAPTER.fromJson(it) }.getOrNull() }
        private fun apiErrorMessage(apiError: ApiError?): String? = apiError?.message ?: apiError?.errors?.values?.flatten()?.firstOrNull()
        private fun apiError(body: String?): String? = apiErrorMessage(parseApiError(body))
        // A server fault's message can be a raw SQL or stack detail when the server runs in debug mode,
        // so customers only ever see a plain explanation for it.
        private fun responseError(response: retrofit2.Response<*>, fallback: String): String =
            if (response.code() >= 500) "Kermit's server ran into a problem, so your request was not completed. Please try again later."
            else apiError(response.errorBody()?.string()) ?: fallback
        fun factory(api: KermitsApi, store: SessionStore) = object : ViewModelProvider.Factory {
            @Suppress("UNCHECKED_CAST")
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                require(modelClass.isAssignableFrom(AppViewModel::class.java))

                return AppViewModel(api, store) as T
            }
        }
    }
}

@Composable
private fun BrandLogo(modifier: Modifier = Modifier) {
    val fallback = painterResource(R.drawable.kermits_logo)
    AsyncImage(
        model = BRAND_LOGO_URL,
        contentDescription = "Kermit's logo",
        modifier = modifier.clip(androidx.compose.foundation.shape.CircleShape),
        placeholder = fallback,
        error = fallback,
        contentScale = ContentScale.Crop,
    )
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun KermitsApp(
    vm: AppViewModel,
    store: SessionStore,
    reservationUpdateId: Int?,
    onReservationUpdateConsumed: () -> Unit,
    orderUpdateId: Int?,
    onOrderUpdateConsumed: () -> Unit,
) {
    val context = androidx.compose.ui.platform.LocalContext.current
    val notificationPermissionLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.RequestPermission(),
    ) { }
    var login by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var registering by rememberSaveable { mutableStateOf(false) }
    var recoveringPassword by rememberSaveable { mutableStateOf(false) }
    var tab by rememberSaveable(vm.signedIn) { mutableIntStateOf(0) }
    var payment by remember { mutableStateOf("cash") }
    var submissionMessage by remember { mutableStateOf<String?>(null) }
    var selectedOrder by remember { mutableStateOf<Order?>(null) }
    var selectedOrderWasJustSubmitted by remember { mutableStateOf(false) }
    var selectedReservation by remember { mutableStateOf<Reservation?>(null) }
    LaunchedEffect(vm.signedIn) {
        if (vm.signedIn) {
            PushNotifications.sync(context)
            if (
                Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
                ContextCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED &&
                PushNotifications.shouldRequestPermission(context, store)
            ) {
                store.notificationPermissionRequested = true
                notificationPermissionLauncher.launch(Manifest.permission.POST_NOTIFICATIONS)
            }
        } else {
            PushNotifications.clearShownNotifications(context)
        }
    }
    LaunchedEffect(vm.signedIn, reservationUpdateId) {
        if (vm.signedIn && reservationUpdateId != null) {
            tab = 1
            vm.loadReservation(reservationUpdateId) { selectedReservation = it }
            onReservationUpdateConsumed()
        }
    }
    LaunchedEffect(vm.signedIn, orderUpdateId) {
        if (vm.signedIn && orderUpdateId != null) {
            tab = 1
            selectedOrderWasJustSubmitted = false
            vm.loadOrder(orderUpdateId) { selectedOrder = it }
            onOrderUpdateConsumed()
        }
    }
    LifecycleResumeEffect(vm.pendingPayMongoOrderId) {
        if (vm.pendingPayMongoOrderId != null) {
            vm.refreshPayMongoOrder { order ->
                if (selectedOrder == null || selectedOrder?.id == order.id) selectedOrder = order
            }
        }
        onPauseOrDispose { }
    }
    vm.recaptchaUrl?.let { url ->
        MobileRecaptchaDialog(
            url = url,
            onVerified = vm::completeRecaptcha,
            onDismiss = vm::cancelRecaptcha,
        )
    }
    if (!vm.signedIn) {
        when {
            recoveringPassword -> PasswordRecoveryScreen(vm, onBack = { recoveringPassword = false })
            registering -> RegistrationScreen(vm, onBack = { registering = false })
            else -> LoginScreen(vm, login, { login = it }, password, { password = it }, onRegister = { vm.clearRegistrationFeedback(); registering = true }, onForgotPassword = { vm.clearRegistrationFeedback(); recoveringPassword = true })
        }
        return
    }
    submissionMessage?.let { message ->
        AlertDialog(
            onDismissRequest = { submissionMessage = null },
            icon = { Icon(Icons.AutoMirrored.Filled.ReceiptLong, contentDescription = null) },
            title = { Text("Request submitted") },
            text = { Text(message) },
            confirmButton = {
                Button(onClick = { submissionMessage = null }) {
                    Text("OK")
                }
            },
        )
    }
    BackHandler(enabled = tab != 0) { tab = 0 }
    Scaffold(
        containerColor = KColors.Canvas,
        bottomBar = { CustomerBottomBar(tab) { tab = it } },
    ) { padding ->
        Column(Modifier.padding(padding).fillMaxSize()) {
            AnimatedVisibility(vm.busy && !vm.refreshing, enter = fadeIn(tween(120)) + expandVertically(tween(140)), exit = fadeOut(tween(100)) + shrinkVertically(tween(140))) {
                LinearProgressIndicator(Modifier.fillMaxWidth().height(2.dp), color = KColors.Lime, trackColor = Color.Transparent)
            }
            AnimatedVisibility(vm.error != null, enter = fadeIn(tween(150)) + expandVertically(tween(160)), exit = fadeOut(tween(100)) + shrinkVertically(tween(140))) {
                vm.error?.let { ErrorBanner(it, onDismiss = vm::clearError, modifier = Modifier.padding(start = 16.dp, end = 16.dp, top = 10.dp)) }
            }
            PullToRefreshBox(
                isRefreshing = vm.refreshing,
                onRefresh = { vm.refresh(pulled = true) },
                modifier = Modifier.fillMaxWidth().weight(1f),
            ) {
                AnimatedContent(
                    targetState = tab,
                    modifier = Modifier.fillMaxSize(),
                    transitionSpec = {
                        val direction = if (targetState > initialState) 1 else -1
                        (fadeIn(tween(210)) + slideInHorizontally(tween(250, easing = FastOutSlowInEasing)) { direction * (it / 18) }) togetherWith
                            (fadeOut(tween(110)) + slideOutHorizontally(tween(190)) { -direction * (it / 24) })
                    },
                    label = "customerDestination",
                ) { destination ->
                    when (destination) {
                        0 -> MenuScreen(
                            vm = vm,
                            payment = payment,
                            setPayment = { payment = it },
                            onOrderSubmitted = { order ->
                                selectedOrderWasJustSubmitted = true
                                selectedOrder = order
                                if (order.payment_method == "paymongo") vm.openPayMongo(context, order.id, order.paymongo_checkout_url)
                            },
                            onNotificationOrder = { id ->
                                selectedOrderWasJustSubmitted = false
                                vm.loadOrder(id) { order -> selectedOrder = order }
                            },
                        )
                        1 -> CustomerHistoryScreen(vm, onOrder = {
                            selectedOrderWasJustSubmitted = false
                            vm.loadOrder(it) { order -> selectedOrder = order }
                        }, onReservation = { vm.loadReservation(it) { selectedReservation = it } }, onReserve = { tab = 2 })
                        2 -> ReservationScreen(vm) { message -> submissionMessage = message }
                        else -> AccountScreen(vm)
                    }
                }
            }
        }
    }
    selectedOrder?.let { order ->
        OrderReceiptDialog(
            order = order,
            customerName = vm.user?.name.orEmpty(),
            wasJustSubmitted = selectedOrderWasJustSubmitted,
            onPayMongo = { vm.openPayMongo(context, order.id); Unit }.takeIf { order.payment_method == "paymongo" && order.payment_status == "pending" },
            close = {
                selectedOrder = null
                selectedOrderWasJustSubmitted = false
            },
        )
    }
    selectedReservation?.let { reservation ->
        ReservationDetailDialog(
            reservation = reservation,
            onPayMongo = reservation.order_id
                ?.takeIf { reservation.payment_method == "paymongo" && reservation.payment_status == "pending" && reservation.status in setOf("pending", "confirmed") }
                ?.let { orderId -> { vm.openPayMongo(context, orderId, reservation.paymongo_checkout_url); Unit } },
            close = { selectedReservation = null },
        )
    }
}

@Composable
private fun LoginScreen(vm: AppViewModel, login: String, setLogin: (String) -> Unit, password: String, setPassword: (String) -> Unit, onRegister: () -> Unit, onForgotPassword: () -> Unit) {
    BoxWithConstraints(Modifier.fillMaxSize().background(KColors.Canvas)) {
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
            Text("KERMIT'S", color = KColors.Lime, fontSize = 12.sp, letterSpacing = 1.8.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(if (compact) 4.dp else 10.dp))
            Text("Good food,\nreserved for you.", color = Color.White, fontSize = if (compact) 25.sp else 34.sp, lineHeight = if (compact) 27.sp else 37.sp, fontWeight = FontWeight.Bold)
            if (!compact) {
                Spacer(Modifier.height(14.dp))
                Text("Order your favorites, reserve the venue, and follow every request in one place.", color = Color(0xFFB9BCB5), fontSize = 15.sp, lineHeight = 23.sp)
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
    val cooldownSeconds = vm.loginCooldownSeconds
    val loginError = if (cooldownSeconds > 0) {
        val unit = if (cooldownSeconds == 1) "second" else "seconds"
        "${vm.loginCooldownReason ?: "Too many login attempts"}. Try again in $cooldownSeconds $unit."
    } else {
        vm.error
    }
    val canLogIn = !vm.busy && cooldownSeconds == 0 && login.isNotBlank() && password.isNotBlank()
    val submitLogin = { if (canLogIn) vm.login(login, password, keepSignedIn) }
    Column(modifier.background(KColors.Canvas).padding(horizontal = 26.dp, vertical = 34.dp), verticalArrangement = Arrangement.Center) {
        Column(Modifier.fillMaxWidth().widthIn(max = 520.dp).align(Alignment.CenterHorizontally)) {
            Text("WELCOME BACK", color = KColors.Lime, fontSize = 12.sp, letterSpacing = 1.8.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(8.dp)); Text("Log in to your account", color = KColors.Ink, fontSize = 30.sp, lineHeight = 35.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(7.dp)); Text("Enter your details to continue to Kermit’s.", color = KColors.Muted, fontSize = 15.sp)
            Spacer(Modifier.height(28.dp))
            OutlinedTextField(login, setLogin, label = { Text("Email Address") }, placeholder = { Text("name@gmail.com") }, singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email, imeAction = ImeAction.Next), colors = loginFieldColors(), shape = RoundedCornerShape(13.dp), modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(16.dp)); OutlinedTextField(password, setPassword, label = { Text("Password") }, placeholder = { Text("Enter your password") }, singleLine = true, visualTransformation = if (passwordVisible) androidx.compose.ui.text.input.VisualTransformation.None else PasswordVisualTransformation(), keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password, imeAction = ImeAction.Done), keyboardActions = KeyboardActions(onDone = { submitLogin() }), trailingIcon = { IconButton(onClick = { passwordVisible = !passwordVisible }) { Icon(if (passwordVisible) Icons.Default.VisibilityOff else Icons.Default.Visibility, if (passwordVisible) "Hide password" else "Show password") } }, colors = loginFieldColors(), shape = RoundedCornerShape(13.dp), modifier = Modifier.fillMaxWidth())
            vm.registrationMessage?.let { Text(it, color = KColors.Olive, fontSize = 13.sp, modifier = Modifier.padding(top = 12.dp)) }
            loginError?.let { Text(it, color = MaterialTheme.colorScheme.error, fontSize = 13.sp, modifier = Modifier.padding(top = 12.dp)) }
            Spacer(Modifier.height(18.dp)); Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) { Row(verticalAlignment = Alignment.CenterVertically) { Checkbox(checked = keepSignedIn, onCheckedChange = { keepSignedIn = it }); Text("Keep me signed in", color = KColors.Muted, fontSize = 13.sp) }; TextButton(onClick = onForgotPassword, contentPadding = PaddingValues(horizontal = 4.dp, vertical = 0.dp)) { Text("Forgot password?", color = KColors.Olive, fontSize = 13.sp, fontWeight = FontWeight.Bold) } }
            Spacer(Modifier.height(15.dp)); Button(onClick = submitLogin, enabled = canLogIn, shape = RoundedCornerShape(13.dp), colors = ButtonDefaults.buttonColors(containerColor = KColors.Ink, contentColor = Color.White), modifier = Modifier.fillMaxWidth().height(56.dp)) { Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) { Text(when { vm.busy -> "Signing in..."; cooldownSeconds > 0 -> "Try again in ${cooldownSeconds}s"; else -> "Log in" }, fontWeight = FontWeight.Bold, fontSize = 16.sp); Text("→", fontSize = 22.sp) } }
            Spacer(Modifier.height(18.dp)); TextButton(onClick = onRegister, modifier = Modifier.fillMaxWidth()) { Text("New customer? Create an account", color = KColors.Olive, fontSize = 13.sp, fontWeight = FontWeight.Bold) }
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

    Surface(color = KColors.Ink, shape = RoundedCornerShape(topStart = 24.dp, topEnd = 24.dp), shadowElevation = 12.dp) {
        NavigationBar(
            containerColor = Color.Transparent,
            contentColor = Color.White,
            tonalElevation = 0.dp,
        ) {
            destinations.forEach { (label, icon, index) ->
                NavigationBarItem(
                    selected = selected == index,
                    onClick = { select(index) },
                    icon = { Icon(icon, contentDescription = null) },
                    label = { Text(label, fontSize = 11.sp, fontWeight = FontWeight.Bold) },
                    alwaysShowLabel = true,
                    colors = NavigationBarItemDefaults.colors(
                        selectedIconColor = KColors.Ink,
                        selectedTextColor = KColors.Lime,
                        indicatorColor = KColors.Lime,
                        unselectedIconColor = Color(0xFFA9ADA4),
                        unselectedTextColor = Color(0xFFA9ADA4),
                    ),
                )
            }
        }
    }
}

@Composable
private fun AccountScreen(vm: AppViewModel) {
    var section by rememberSaveable { mutableStateOf("account") }
    BackHandler(enabled = section != "account") { section = "account"; vm.clearError() }

    when (section) {
        "personal" -> PersonalInformationScreen(vm) { section = "account"; vm.clearError() }
        "password" -> ChangePasswordScreen(vm) { section = "account"; vm.clearError() }
        else -> Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(horizontal = 16.dp, vertical = 18.dp)) {
            val user = vm.user
            ScreenHeader("Your profile", "Account", subtitle = "Manage your details and security.")
            Spacer(Modifier.height(18.dp))
            Surface(Modifier.fillMaxWidth(), shape = RoundedCornerShape(22.dp), color = KColors.Ink) {
                Row(Modifier.padding(18.dp), verticalAlignment = Alignment.CenterVertically) {
                    Box(Modifier.size(60.dp).background(KColors.Lime, androidx.compose.foundation.shape.CircleShape), contentAlignment = Alignment.Center) {
                        Text(initials(user?.name.orEmpty()), color = KColors.Ink, fontSize = 22.sp, fontWeight = FontWeight.Black)
                    }
                    Column(Modifier.weight(1f).padding(start = 14.dp)) {
                        Text(user?.name.orEmpty(), color = Color.White, fontSize = 19.sp, fontWeight = FontWeight.Bold, maxLines = 1, overflow = TextOverflow.Ellipsis)
                        Text(user?.email.orEmpty(), color = Color(0xFFC5C8BF), fontSize = 13.sp, maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.padding(top = 3.dp))
                        user?.phone?.takeIf { it.isNotBlank() }?.let { Text(it, color = Color(0xFFC5C8BF), fontSize = 13.sp, modifier = Modifier.padding(top = 2.dp)) }
                    }
                }
            }
            Spacer(Modifier.height(22.dp))
            Eyebrow("Settings", color = KColors.Muted)
            Spacer(Modifier.height(10.dp))
            KCard(Modifier.fillMaxWidth(), contentPadding = 6.dp) {
                AccountOption(Icons.Default.Person, "Personal information", "Name, phone number, and address") { vm.clearError(); section = "personal" }
                HorizontalDivider(color = KColors.Line, modifier = Modifier.padding(horizontal = 12.dp))
                AccountOption(Icons.Default.Lock, "Change password", "Verify your email, then choose a new password") { vm.clearError(); section = "password" }
            }
            Spacer(Modifier.height(22.dp))
            var confirmingLogout by rememberSaveable { mutableStateOf(false) }
            SecondaryButton("Log out", onClick = { confirmingLogout = true }, contentColor = KColors.Danger)
            if (confirmingLogout) {
                AlertDialog(
                    onDismissRequest = { confirmingLogout = false },
                    icon = { Icon(Icons.AutoMirrored.Filled.Logout, contentDescription = null, tint = KColors.Danger) },
                    title = { Text("Log out of Kermit's?") },
                    text = { Text("You will need to sign in again to order or reserve.") },
                    confirmButton = {
                        Button(
                            onClick = { confirmingLogout = false; vm.logout() },
                            colors = ButtonDefaults.buttonColors(containerColor = KColors.Danger, contentColor = Color.White),
                        ) { Text("Log out", fontWeight = FontWeight.Bold) }
                    },
                    dismissButton = { TextButton(onClick = { confirmingLogout = false }) { Text("Cancel", color = KColors.Ink) } },
                    containerColor = KColors.Surface,
                )
            }
            Text("Kermit's app ${BuildConfig.VERSION_NAME}", color = KColors.Faint, fontSize = 12.sp, textAlign = TextAlign.Center, modifier = Modifier.fillMaxWidth().padding(top = 16.dp))
        }
    }
}

private fun initials(name: String): String =
    name.split(' ').filter(String::isNotBlank).take(2).joinToString("") { it.take(1).uppercase() }.ifEmpty { "K" }

@Composable
private fun AccountOption(icon: ImageVector, title: String, subtitle: String, onClick: () -> Unit) {
    Surface(onClick = onClick, modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp), color = Color.Transparent) {
        Row(Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
            Box(Modifier.size(42.dp).background(KColors.LimeSoft, RoundedCornerShape(12.dp)), contentAlignment = Alignment.Center) {
                Icon(icon, contentDescription = null, tint = KColors.Olive, modifier = Modifier.size(21.dp))
            }
            Column(Modifier.weight(1f).padding(horizontal = 12.dp)) {
                Text(title, fontWeight = FontWeight.Bold, fontSize = 15.sp)
                Text(subtitle, color = KColors.Muted, fontSize = 12.sp, lineHeight = 17.sp, modifier = Modifier.padding(top = 2.dp))
            }
            Icon(Icons.Default.ChevronRight, contentDescription = null, tint = KColors.Faint)
        }
    }
}

/** Header for screens opened from Account, with a back arrow in place of the tab title. */
@Composable
private fun SubScreenHeader(title: String, subtitle: String, enabled: Boolean, onBack: () -> Unit) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        FilledIconButton(onClick = onBack, enabled = enabled, colors = IconButtonDefaults.filledIconButtonColors(containerColor = Color.White, contentColor = KColors.Ink)) {
            Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back to Account")
        }
        Column(Modifier.padding(start = 12.dp)) {
            Text(title, fontSize = 24.sp, fontWeight = FontWeight.Black, modifier = Modifier.semantics { heading() })
            Text(subtitle, color = KColors.Muted, fontSize = 13.sp, lineHeight = 18.sp)
        }
    }
}

@Composable
private fun CurrentLocationAddressButton(onAddressFound: (String) -> Unit) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var locating by rememberSaveable { mutableStateOf(false) }
    var locationMessage by rememberSaveable { mutableStateOf<String?>(null) }

    val findAddress: () -> Unit = {
        scope.launch {
            locating = true
            locationMessage = "Finding your present location..."
            val address = currentNamedAddress(context)

            if (address == null) {
                locationMessage = "Your location name could not be found. Enter your address manually."
            } else {
                onAddressFound(address)
                locationMessage = "Present location added."
            }

            locating = false
        }
    }
    val permissionLauncher = rememberLauncherForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) { permissions ->
        if (permissions.values.any { it }) {
            findAddress()
        } else {
            locationMessage = "Location permission was denied. Enter your address manually."
        }
    }
    val hasLocationPermission = {
        ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED ||
            ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_COARSE_LOCATION) == PackageManager.PERMISSION_GRANTED
    }

    OutlinedButton(
        onClick = {
            locationMessage = null
            if (hasLocationPermission()) {
                findAddress()
            } else {
                permissionLauncher.launch(arrayOf(Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION))
            }
        },
        enabled = !locating,
        shape = RoundedCornerShape(10.dp),
        modifier = Modifier.fillMaxWidth().padding(bottom = 4.dp),
    ) {
        Text(if (locating) "Finding location..." else "Use my current location", fontWeight = FontWeight.Bold)
    }
    locationMessage?.let {
        Text(it, color = if (it == "Present location added.") Color(0xFF267444) else MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 12.sp, modifier = Modifier.padding(bottom = 8.dp))
    }
}

@SuppressLint("MissingPermission")
@Suppress("DEPRECATION")
private suspend fun currentDeviceLocation(context: Context): Location? {
    val manager = context.getSystemService(Context.LOCATION_SERVICE) as LocationManager
    val providers = listOf(LocationManager.GPS_PROVIDER, LocationManager.NETWORK_PROVIDER)
        .filter { provider -> runCatching { manager.isProviderEnabled(provider) }.getOrDefault(false) }
    val cachedLocation = providers
        .mapNotNull { provider -> runCatching { manager.getLastKnownLocation(provider) }.getOrNull() }
        .maxByOrNull(Location::getTime)
    val provider = providers.firstOrNull() ?: return cachedLocation

    return withTimeoutOrNull(12_000) {
        suspendCancellableCoroutine { continuation ->
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
                val cancellationSignal = CancellationSignal()
                continuation.invokeOnCancellation { cancellationSignal.cancel() }
                manager.getCurrentLocation(provider, cancellationSignal, ContextCompat.getMainExecutor(context)) { location ->
                    if (continuation.isActive) continuation.resume(location ?: cachedLocation)
                }
            } else {
                val listener = object : LocationListener {
                    override fun onLocationChanged(location: Location) {
                        manager.removeUpdates(this)
                        if (continuation.isActive) continuation.resume(location)
                    }

                    override fun onProviderDisabled(provider: String) {
                        manager.removeUpdates(this)
                        if (continuation.isActive) continuation.resume(cachedLocation)
                    }

                    @Deprecated("Deprecated by Android")
                    override fun onStatusChanged(provider: String?, status: Int, extras: Bundle?) = Unit
                }
                continuation.invokeOnCancellation { manager.removeUpdates(listener) }
                manager.requestSingleUpdate(provider, listener, Looper.getMainLooper())
            }
        }
    } ?: cachedLocation
}

@Suppress("DEPRECATION")
private suspend fun currentNamedAddress(context: Context): String? {
    val location = currentDeviceLocation(context) ?: return null

    return withContext(Dispatchers.IO) {
        if (!Geocoder.isPresent()) return@withContext null

        runCatching {
            Geocoder(context, Locale.ENGLISH)
                .getFromLocation(location.latitude, location.longitude, 1)
                ?.firstOrNull()
                ?.toNamedLocation()
        }.getOrNull()
    }
}

private fun Address.toNamedLocation(): String? {
    fun namedPart(value: String?): String? = value
        ?.trim()
        ?.takeIf(String::isNotEmpty)
        ?.takeUnless { PLUS_CODE.matches(it) }

    val street = listOfNotNull(namedPart(subThoroughfare), namedPart(thoroughfare))
        .joinToString(" ")
        .takeIf(String::isNotEmpty)
    val parts = listOf(
        street,
        namedPart(featureName),
        namedPart(subLocality),
        namedPart(locality),
        namedPart(subAdminArea),
        namedPart(adminArea),
        namedPart(countryName),
    ).filterNotNull().fold(mutableListOf<String>()) { unique, part ->
        if (unique.none { it.equals(part, ignoreCase = true) }) unique += part
        unique
    }

    return parts.joinToString(", ").takeIf(String::isNotEmpty)
}

private val PLUS_CODE = Regex("^[23456789CFGHJMPQRVWX]{4,8}\\+[23456789CFGHJMPQRVWX]{2,8}$", RegexOption.IGNORE_CASE)

@Composable
private fun PersonalInformationScreen(vm: AppViewModel, onBack: () -> Unit) {
    val customer = vm.user
    var name by rememberSaveable(customer?.id) { mutableStateOf(customer?.name.orEmpty()) }
    var phone by rememberSaveable(customer?.id) { mutableStateOf(customer?.phone.orEmpty()) }
    var address by rememberSaveable(customer?.id) { mutableStateOf(customer?.address.orEmpty()) }
    var status by rememberSaveable { mutableStateOf<String?>(null) }
    val canSave = name.isNotBlank() && name.length <= 100 && Regex("^09\\d{9}$").matches(phone) && address.isNotBlank() && address.length <= 500

    Column(Modifier.fillMaxSize().imePadding().verticalScroll(rememberScrollState()).padding(horizontal = 16.dp, vertical = 18.dp)) {
        SubScreenHeader("Personal information", "The details used to identify and contact you.", enabled = !vm.busy, onBack = onBack)
        Spacer(Modifier.height(18.dp))
        KCard(Modifier.fillMaxWidth()) {
            Column {
                RegistrationField("Full name", name, "Maximum 100 characters") { name = it.take(100); status = null; vm.clearError() }
                RegistrationField("Phone number", phone, "11 digits starting with 09", keyboardType = KeyboardType.Number) { phone = it.filter(Char::isDigit).take(11); status = null; vm.clearError() }
                OutlinedTextField(
                    value = address,
                    onValueChange = { address = it.take(500); status = null; vm.clearError() },
                    label = { Text("Present address") },
                    placeholder = { Text("e.g. Binaobao, Bantayan, Cebu, Philippines") },
                    supportingText = { Text("${address.length}/500 characters") },
                    minLines = 3,
                    maxLines = 5,
                    colors = loginFieldColors(),
                    shape = RoundedCornerShape(12.dp),
                    modifier = Modifier.fillMaxWidth().padding(bottom = 4.dp),
                )
                CurrentLocationAddressButton { foundAddress -> address = foundAddress.take(500); status = null; vm.clearError() }
                OutlinedTextField(
                    value = customer?.email.orEmpty(),
                    onValueChange = {},
                    label = { Text("Email address") },
                    supportingText = { Text("Your verified email address cannot be changed.") },
                    readOnly = true,
                    singleLine = true,
                    colors = loginFieldColors(),
                    shape = RoundedCornerShape(12.dp),
                    modifier = Modifier.fillMaxWidth(),
                )
                status?.let { Text(it, color = KColors.Success, fontSize = 13.sp, modifier = Modifier.padding(top = 8.dp)) }
                Spacer(Modifier.height(16.dp))
                PrimaryButton(
                    if (vm.busy) "Saving..." else "Save changes",
                    onClick = { status = null; vm.updateProfile(name, phone, address) { status = it } },
                    enabled = canSave && !vm.busy,
                )
            }
        }
        Spacer(Modifier.height(20.dp))
    }
}

@Composable
private fun ChangePasswordScreen(vm: AppViewModel, onBack: () -> Unit) {
    var code by rememberSaveable { mutableStateOf("") }
    var newPassword by remember { mutableStateOf("") }
    var confirmation by remember { mutableStateOf("") }
    var sentMessage by rememberSaveable { mutableStateOf<String?>(null) }
    val passwordIsStrong = newPassword.length in 8..23 && newPassword.any(Char::isUpperCase) && newPassword.any(Char::isLowerCase) && newPassword.any(Char::isDigit) && Regex("[\\p{Z}\\p{S}\\p{P}]").containsMatchIn(newPassword)
    val canChange = code.length == 6 && passwordIsStrong && confirmation == newPassword

    Column(Modifier.fillMaxSize().imePadding().verticalScroll(rememberScrollState()).padding(horizontal = 16.dp, vertical = 18.dp)) {
        SubScreenHeader("Change password", "Verify your email before choosing a new password.", enabled = !vm.busy, onBack = onBack)
        Spacer(Modifier.height(18.dp))
        SectionCard("Verify your email", step = 1, subtitle = "We'll send a one-time code to ${vm.user?.email.orEmpty()}. It expires after 10 minutes.") {
            SecondaryButton(if (vm.busy) "Sending..." else "Send verification code", onClick = { sentMessage = null; vm.sendPasswordVerificationCode { sentMessage = it } }, enabled = !vm.busy)
            sentMessage?.let { Text(it, color = KColors.Success, fontSize = 13.sp, lineHeight = 18.sp, modifier = Modifier.padding(top = 10.dp)) }
        }
        Spacer(Modifier.height(14.dp))
        SectionCard("Choose a new password", step = 2) {
            RegistrationField("Email verification code", code, "Enter the 6-digit code", keyboardType = KeyboardType.NumberPassword) { code = it.filter(Char::isDigit).take(6); vm.clearError() }
            RegistrationField("New password", newPassword, "8-23 characters with uppercase, lowercase, number, and symbol", password = true, keyboardType = KeyboardType.Password) { newPassword = it.take(23); vm.clearError() }
            RegistrationField("Confirm new password", confirmation, "Enter the new password again", password = true, keyboardType = KeyboardType.Password, imeAction = ImeAction.Done) { confirmation = it.take(23); vm.clearError() }
            Spacer(Modifier.height(10.dp))
            PrimaryButton(if (vm.busy) "Changing password..." else "Change password", onClick = { vm.changePassword(code, newPassword, confirmation) {} }, enabled = canChange && !vm.busy)
        }
        Spacer(Modifier.height(20.dp))
    }
}

@Composable
private fun PasswordRecoveryScreen(vm: AppViewModel, onBack: () -> Unit) {
    var email by rememberSaveable { mutableStateOf("") }
    var challenge by rememberSaveable { mutableStateOf<String?>(null) }
    var code by rememberSaveable { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var confirmation by remember { mutableStateOf("") }
    var showErrors by remember { mutableStateOf(false) }
    var resendSeconds by rememberSaveable { mutableIntStateOf(0) }
    LaunchedEffect(resendSeconds) {
        if (resendSeconds > 0) { delay(1000); resendSeconds-- }
    }
    LaunchedEffect(vm.registrationNeedsVerification) {
        if (vm.registrationNeedsVerification) { challenge = null; code = ""; resendSeconds = 0 }
    }
    val validEmail = android.util.Patterns.EMAIL_ADDRESS.matcher(email.trim()).matches()
    val passwordError = passwordRuleError(password)
    val confirmationError = passwordConfirmationError(password, confirmation)
    val sendCode = {
        vm.requestPasswordReset(email) { issuedChallenge ->
            if (issuedChallenge != null) { challenge = issuedChallenge; code = ""; resendSeconds = 60 }
        }
    }

    Column(Modifier.fillMaxSize().background(KColors.Canvas).imePadding().padding(horizontal = 26.dp, vertical = 28.dp)) {
        TextButton(onClick = { vm.clearRegistrationFeedback(); onBack() }, enabled = !vm.busy, contentPadding = PaddingValues(0.dp)) { Text("← Back to log in", color = KColors.Olive, fontWeight = FontWeight.Bold) }
        Column(Modifier.fillMaxWidth().widthIn(max = 520.dp).weight(1f).align(Alignment.CenterHorizontally).verticalScroll(rememberScrollState()), verticalArrangement = Arrangement.Center) {
            BrandLogo(Modifier.size(72.dp).align(Alignment.CenterHorizontally).background(Color.White, androidx.compose.foundation.shape.CircleShape).padding(5.dp))
            Spacer(Modifier.height(22.dp))
            Text("RESET PASSWORD", color = KColors.Lime, fontSize = 12.sp, letterSpacing = 1.8.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(8.dp))
            Text("Recover your account", color = KColors.Ink, fontSize = 30.sp, lineHeight = 35.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(7.dp))
            Text(
                if (challenge == null) "Enter the email address used by your customer account. We’ll email you a 6-digit reset code."
                else "Enter the 6-digit code from your email, then choose a new password.",
                color = KColors.Muted, fontSize = 14.sp, lineHeight = 21.sp,
            )
            Spacer(Modifier.height(24.dp))
            OutlinedTextField(email, { email = it; vm.clearError() }, label = { Text("Email address") }, placeholder = { Text("name@gmail.com") }, enabled = challenge == null && !vm.busy, singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email, imeAction = ImeAction.Done), keyboardActions = KeyboardActions(onDone = { if (validEmail && !vm.busy && challenge == null) sendCode() }), colors = loginFieldColors(), shape = RoundedCornerShape(13.dp), modifier = Modifier.fillMaxWidth())
            if (challenge == null) {
                Spacer(Modifier.height(18.dp))
                Button(onClick = sendCode, enabled = validEmail && !vm.busy, shape = RoundedCornerShape(13.dp), colors = ButtonDefaults.buttonColors(containerColor = KColors.Ink, contentColor = Color.White), modifier = Modifier.fillMaxWidth().height(54.dp)) { Text(if (vm.busy) "Sending..." else "Send reset code", fontWeight = FontWeight.Bold) }
            } else {
                Spacer(Modifier.height(12.dp))
                OutlinedTextField(code, { code = it.filter { digit -> digit in '0'..'9' }.take(6); vm.clearError() }, label = { Text("6-digit reset code") }, enabled = !vm.busy, singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.NumberPassword, imeAction = ImeAction.Next), colors = loginFieldColors(), shape = RoundedCornerShape(12.dp), modifier = Modifier.fillMaxWidth().padding(bottom = 8.dp))
                RegistrationField(
                    label = "New password",
                    value = password,
                    helperText = "8-23 characters with uppercase, lowercase, a number, and a symbol (${password.length}/23)",
                    errorText = passwordError.takeIf { showErrors },
                    password = true,
                    keyboardType = KeyboardType.Password,
                ) { input -> password = input.take(23); vm.clearError() }
                RegistrationField(
                    label = "Confirm new password",
                    value = confirmation,
                    helperText = "Must exactly match your new password (${confirmation.length}/23)",
                    errorText = confirmationError.takeIf { showErrors },
                    password = true,
                    keyboardType = KeyboardType.Password,
                    imeAction = ImeAction.Done,
                ) { input -> confirmation = input.take(23); vm.clearError() }
                Spacer(Modifier.height(12.dp))
                Button(
                    onClick = {
                        showErrors = true
                        vm.clearError()
                        if (passwordError == null && confirmationError == null) {
                            challenge?.let { vm.resetPassword(it, email, code, password, confirmation) { ok -> if (ok) onBack() } }
                        }
                    },
                    enabled = code.length == 6 && !vm.busy,
                    shape = RoundedCornerShape(13.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = KColors.Ink, contentColor = Color.White),
                    modifier = Modifier.fillMaxWidth().height(54.dp),
                ) { Text(if (vm.busy) "Resetting password..." else "Reset password", fontWeight = FontWeight.Bold) }
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    TextButton(onClick = { challenge = null; code = ""; resendSeconds = 0; vm.clearRegistrationFeedback() }, enabled = !vm.busy) { Text("Change email") }
                    TextButton(onClick = sendCode, enabled = !vm.busy && resendSeconds == 0) { Text(if (resendSeconds > 0) "Resend in ${resendSeconds}s" else "Resend code") }
                }
            }
            vm.error?.let { Text(it, color = MaterialTheme.colorScheme.error, fontSize = 13.sp, modifier = Modifier.padding(top = 12.dp)) }
            vm.registrationMessage?.let { Text(it, color = KColors.Olive, fontSize = 13.sp, lineHeight = 19.sp, modifier = Modifier.padding(top = 12.dp)) }
        }
    }
}

private fun passwordRuleError(password: String): String? = when {
    password.isBlank() -> "Enter a password."
    password.length !in 8..23 ||
        password.none(Char::isUpperCase) ||
        password.none(Char::isLowerCase) ||
        !Regex("\\p{N}").containsMatchIn(password) ||
        !Regex("[\\p{Z}\\p{S}\\p{P}]").containsMatchIn(password) ->
        "Password must be 8-23 characters with uppercase, lowercase, a number, and a symbol."
    else -> null
}

private fun passwordConfirmationError(password: String, confirmation: String): String? = when {
    confirmation.isBlank() -> "Confirm your password."
    confirmation != password -> "Passwords do not match."
    else -> null
}

@Composable
private fun RegistrationScreen(vm: AppViewModel, onBack: () -> Unit) {
    var email by rememberSaveable { mutableStateOf("") }; var challenge by rememberSaveable { mutableStateOf<String?>(null) }; var code by rememberSaveable { mutableStateOf("") }; var token by rememberSaveable { mutableStateOf<String?>(null) }
    var name by rememberSaveable { mutableStateOf("") }; var phone by rememberSaveable { mutableStateOf("") }; var birthday by rememberSaveable { mutableStateOf("") }; var sex by rememberSaveable { mutableStateOf("") }; var address by rememberSaveable { mutableStateOf("") }; var password by remember { mutableStateOf("") }; var confirmation by remember { mutableStateOf("") }
    val context = LocalContext.current
    var resendSeconds by rememberSaveable { mutableIntStateOf(0) }
    LaunchedEffect(resendSeconds) {
        if (resendSeconds > 0) { delay(1000); resendSeconds-- }
    }
    LaunchedEffect(vm.registrationNeedsVerification) {
        if (vm.registrationNeedsVerification) { challenge = null; token = null; code = ""; resendSeconds = 0 }
    }
    var showRegistrationErrors by remember { mutableStateOf(false) }
    val validGmail = email.trim().matches(Regex("^[^@\\s]+@gmail\\.com$", RegexOption.IGNORE_CASE))
    val normalizedName = name.trim()
    val nameError = when {
        normalizedName.isBlank() -> "Enter your full name."
        name.length > 100 || !normalizedName.matches(Regex("^\\p{L}[\\p{L}\\p{M}]*(?: \\p{L}[\\p{L}\\p{M}]*)*$")) ->
            "Full name can contain only letters and single spaces, up to 100 characters."
        else -> null
    }
    val phoneError = when {
        phone.isBlank() -> "Enter your phone number."
        phone.length != 11 -> "Phone number must contain exactly 11 digits."
        !phone.startsWith("09") -> "Phone number must start with 09."
        else -> null
    }
    val birthdayError = if (calculateAge(birthday) == null) "Select a valid birthday." else null
    val sexError = if (sex !in setOf("male", "female")) "Select your sex." else null
    val addressError = when {
        address.isBlank() -> "Enter your address."
        address.length > 500 -> "Address must not be more than 500 characters."
        else -> null
    }
    val passwordError = passwordRuleError(password)
    val confirmationError = passwordConfirmationError(password, confirmation)
    val firstRegistrationError = nameError ?: phoneError ?: birthdayError ?: sexError ?: addressError ?: passwordError ?: confirmationError
    Column(Modifier.fillMaxSize().background(KColors.Canvas).imePadding()) {
        RegistrationBrandPanel(Modifier.fillMaxWidth().height(170.dp))
        Column(Modifier.fillMaxWidth().weight(1f).verticalScroll(rememberScrollState()).padding(horizontal = 20.dp, vertical = 24.dp)) {
        TextButton(onClick = onBack, enabled = !vm.busy, contentPadding = PaddingValues(0.dp)) { Text("← Back to log in", color = KColors.Olive, fontWeight = FontWeight.Bold) }
        Spacer(Modifier.height(12.dp)); Text("SIGN UP", color = KColors.Lime, fontSize = 12.sp, letterSpacing = 1.8.sp, fontWeight = FontWeight.Bold); Text("Create your account", fontSize = 30.sp, fontWeight = FontWeight.Bold); Text("Verify your Gmail first, then create your customer account securely.", color = KColors.Muted, modifier = Modifier.padding(top = 7.dp))
        Spacer(Modifier.height(22.dp)); Text("Step 1  Gmail verification", fontWeight = FontWeight.Bold); Text("Use a Gmail address you can open now.", color = KColors.Muted, fontSize = 12.sp, modifier = Modifier.padding(top = 3.dp)); Spacer(Modifier.height(10.dp))
        OutlinedTextField(email, { email = it; vm.clearError() }, label = { Text("Gmail address") }, placeholder = { Text("name@gmail.com") }, enabled = challenge == null && !vm.busy, singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email), colors = loginFieldColors(), shape = RoundedCornerShape(12.dp), modifier = Modifier.fillMaxWidth())
        if (token == null) {
            Spacer(Modifier.height(8.dp))
            OutlinedButton(onClick = {
                vm.sendCode(email) { issuedChallenge ->
                    if (issuedChallenge != null) { challenge = issuedChallenge; code = ""; resendSeconds = 60 }
                }
            }, enabled = validGmail && !vm.busy && resendSeconds == 0, shape = RoundedCornerShape(10.dp), modifier = Modifier.fillMaxWidth().height(48.dp)) {
                Text(when { resendSeconds > 0 -> "Resend in ${resendSeconds}s"; challenge != null -> "Resend code"; else -> "Send code" }, fontWeight = FontWeight.Bold)
            }
        }
        if (challenge != null && token == null) {
            Spacer(Modifier.height(12.dp))
            OutlinedTextField(code, { code = it.filter { digit -> digit in '0'..'9' }.take(6); vm.clearError() }, label = { Text("6-digit verification code") }, enabled = !vm.busy, singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.NumberPassword), colors = loginFieldColors(), shape = RoundedCornerShape(12.dp), modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(8.dp))
            Button(onClick = { challenge?.let { vm.verifyCode(it, email, code) { verified -> token = verified } } }, enabled = code.length == 6 && !vm.busy, colors = ButtonDefaults.buttonColors(containerColor = KColors.Ink), shape = RoundedCornerShape(10.dp), modifier = Modifier.fillMaxWidth().height(48.dp)) { Text(if (vm.busy) "Please wait..." else "Verify Gmail", fontWeight = FontWeight.Bold) }
        }
        if (challenge != null || token != null) {
            TextButton(onClick = { challenge = null; token = null; code = ""; resendSeconds = 0; vm.clearRegistrationFeedback() }, enabled = !vm.busy) { Text("Change email / verify again") }
        }
        if (token != null) {
            Spacer(Modifier.height(22.dp))
            Text("Step 2  Account details", fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(10.dp))
            RegistrationField(
                label = "Full name",
                value = name,
                helperText = "Letters and single spaces only (${name.length}/100)",
                errorText = nameError.takeIf { showRegistrationErrors },
                keyboardType = KeyboardType.Text,
            ) { input -> name = input.filter { it.isLetter() || it == ' ' }.take(100) }
            RegistrationField(
                label = "Phone number",
                value = phone,
                helperText = "Exactly 11 digits, starting with 09 (${phone.length}/11)",
                errorText = phoneError.takeIf { showRegistrationErrors },
                keyboardType = KeyboardType.Number,
            ) { input -> phone = input.filter { it in '0'..'9' }.take(11) }
            BirthdayPickerField(birthday, birthdayError.takeIf { showRegistrationErrors }) { showBirthdayPicker(context) { birthday = it } }
            OutlinedTextField(
                value = calculateAge(birthday)?.let { "$it years old" }.orEmpty(),
                onValueChange = {},
                label = { Text("Age") },
                supportingText = { Text("Calculated automatically from your birthday") },
                readOnly = true,
                singleLine = true,
                colors = loginFieldColors(),
                shape = RoundedCornerShape(12.dp),
                modifier = Modifier.fillMaxWidth().padding(bottom = 4.dp),
            )
            Text("Sex", fontWeight = FontWeight.Bold, fontSize = 13.sp, modifier = Modifier.padding(top = 5.dp, bottom = 5.dp))
            Row(Modifier.fillMaxWidth().horizontalScroll(rememberScrollState())) {
                listOf("male" to "Male", "female" to "Female").forEach { (value, label) ->
                    KChip(selected = sex == value, label = label, onClick = { sex = value })
                }
            }
            if (showRegistrationErrors && sexError != null) Text(sexError, color = MaterialTheme.colorScheme.error, fontSize = 12.sp, modifier = Modifier.padding(bottom = 5.dp))
            OutlinedTextField(
                value = address,
                onValueChange = { address = it.take(500) },
                label = { Text("Address") },
                placeholder = { Text("e.g. Binaobao, Bantayan, Cebu, Philippines") },
                supportingText = { Text(if (showRegistrationErrors && addressError != null) addressError else "${address.length}/500 characters") },
                isError = showRegistrationErrors && addressError != null,
                minLines = 3,
                maxLines = 5,
                colors = loginFieldColors(),
                shape = RoundedCornerShape(12.dp),
                modifier = Modifier.fillMaxWidth().padding(bottom = 4.dp),
            )
            CurrentLocationAddressButton { foundAddress -> address = foundAddress.take(500); vm.clearError() }
            RegistrationField(
                label = "Password",
                value = password,
                helperText = "8-23 characters with uppercase, lowercase, a number, and a symbol (${password.length}/23)",
                errorText = passwordError.takeIf { showRegistrationErrors },
                password = true,
                keyboardType = KeyboardType.Password,
            ) { input -> password = input.take(23) }
            RegistrationField(
                label = "Confirm password",
                value = confirmation,
                helperText = "Must exactly match your password (${confirmation.length}/23)",
                errorText = confirmationError.takeIf { showRegistrationErrors },
                password = true,
                keyboardType = KeyboardType.Password,
                imeAction = ImeAction.Done,
            ) { input -> confirmation = input.take(23) }
            Spacer(Modifier.height(12.dp))
            Button(
                onClick = {
                    showRegistrationErrors = true
                    vm.clearError()
                    if (firstRegistrationError == null) {
                        vm.register(RegisterRequest(token!!, normalizedName, email.trim(), phone, birthday, sex, address.trim(), password, confirmation)) { ok -> if (ok) onBack() }
                    }
                },
                enabled = !vm.busy,
                modifier = Modifier.fillMaxWidth(),
            ) { Text(if (vm.busy) "Creating account..." else "Create account") }
            if (showRegistrationErrors && firstRegistrationError != null) {
                Text(firstRegistrationError, color = MaterialTheme.colorScheme.error, fontSize = 13.sp, modifier = Modifier.padding(top = 10.dp))
            }
        }
        vm.error?.let { Text(it, color = MaterialTheme.colorScheme.error, fontSize = 13.sp, modifier = Modifier.padding(top = 12.dp)) }; vm.registrationMessage?.let { Text(it, color = MaterialTheme.colorScheme.primary, fontSize = 13.sp, modifier = Modifier.padding(top = 12.dp)) }
        }
    }
}

@Composable
private fun BirthdayPickerField(value: String, error: String?, onClick: () -> Unit) {
    val shape = RoundedCornerShape(12.dp)
    Box(Modifier.fillMaxWidth().padding(bottom = 4.dp)) {
        OutlinedTextField(
            value = value,
            onValueChange = {},
            label = { Text("Birthday") },
            placeholder = { Text("Select birthday") },
            supportingText = { Text(error ?: "Your age will be calculated automatically") },
            isError = error != null,
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
                .semantics { contentDescription = if (value.isBlank()) "Select birthday" else "Birthday selected $value" },
        )
    }
}

private fun showBirthdayPicker(context: Context, onSelected: (String) -> Unit) {
    val selected = LocalDate.now().minusYears(18)
    DatePickerDialog(
        context,
        { _, year, month, day -> onSelected("%04d-%02d-%02d".format(Locale.US, year, month + 1, day)) },
        selected.year,
        selected.monthValue - 1,
        selected.dayOfMonth,
    ).apply {
        datePicker.minDate = java.util.GregorianCalendar(1900, Calendar.JANUARY, 1).timeInMillis
        datePicker.maxDate = System.currentTimeMillis()
    }.show()
}

private fun calculateAge(birthday: String): Int? = runCatching {
    val birthDate = LocalDate.parse(birthday)
    if (birthDate.isAfter(LocalDate.now())) null else Period.between(birthDate, LocalDate.now()).years.takeIf { it >= 0 }
}.getOrNull()

@Composable
private fun RegistrationBrandPanel(modifier: Modifier = Modifier) {
    Row(modifier.background(Brush.linearGradient(listOf(Color(0xFF131413), Color(0xFF1C1E1A), Color(0xFF30332B)))).padding(22.dp), verticalAlignment = Alignment.CenterVertically) {
        BrandLogo(Modifier.size(62.dp).background(Color.White, androidx.compose.foundation.shape.CircleShape).padding(5.dp))
        Spacer(Modifier.width(18.dp))
        Column {
            Text("CUSTOMER ACCOUNT", color = KColors.Lime, fontSize = 11.sp, letterSpacing = 1.6.sp, fontWeight = FontWeight.Bold)
            Text("Order your\nfavorites.", color = Color.White, fontSize = 27.sp, lineHeight = 29.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(top = 5.dp))
        }
    }
}

@Composable
private fun RegistrationField(
    label: String,
    value: String,
    helperText: String,
    errorText: String? = null,
    password: Boolean = false,
    keyboardType: KeyboardType = KeyboardType.Text,
    imeAction: ImeAction = ImeAction.Next,
    onChange: (String) -> Unit,
) {
    OutlinedTextField(
        value = value,
        onValueChange = onChange,
        label = { Text(label) },
        supportingText = { Text(errorText ?: helperText) },
        isError = errorText != null,
        singleLine = true,
        visualTransformation = if (password) PasswordVisualTransformation() else androidx.compose.ui.text.input.VisualTransformation.None,
        keyboardOptions = KeyboardOptions(keyboardType = keyboardType, imeAction = imeAction),
        colors = loginFieldColors(),
        shape = RoundedCornerShape(12.dp),
        modifier = Modifier.fillMaxWidth().padding(bottom = 4.dp),
    )
}

@Composable
private fun loginFieldColors() = OutlinedTextFieldDefaults.colors(
    focusedBorderColor = KColors.Olive, unfocusedBorderColor = KColors.Line,
    focusedLabelColor = KColors.Olive, unfocusedLabelColor = KColors.Muted,
    focusedTextColor = KColors.Ink, unfocusedTextColor = KColors.Ink,
    cursorColor = KColors.Olive, focusedContainerColor = Color.White, unfocusedContainerColor = Color.White,
    disabledContainerColor = KColors.Canvas, disabledBorderColor = KColors.Line,
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
                    if (hour < 8 || hour > 22 || (hour == 22 && minute > 0)) {
                        android.widget.Toast.makeText(context, "Choose an arrival from 8:00 AM to 10:00 PM.", android.widget.Toast.LENGTH_LONG).show()
                        return@TimePickerDialog
                    }
                    calendar.set(Calendar.SECOND, 0)
                    calendar.set(Calendar.MILLISECOND, 0)
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

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun MenuScreen(vm: AppViewModel, payment: String, setPayment: (String) -> Unit, onOrderSubmitted: (Order) -> Unit, onNotificationOrder: (Int) -> Unit) {
    var query by remember { mutableStateOf("") }
    var category by remember { mutableStateOf("All") }
    var cartOpen by rememberSaveable { mutableStateOf(false) }
    var notificationsOpen by rememberSaveable { mutableStateOf(false) }
    var checkingOut by rememberSaveable { mutableStateOf(false) }
    var phone by remember { mutableStateOf(vm.user?.phone.orEmpty()) }
    var date by remember { mutableStateOf("") }
    var tableSize by remember { mutableStateOf("4") }
    LaunchedEffect(vm.tableSizes) { if (tableSize !in vm.tableSizes) tableSize = vm.tableSizes.first() }
    var diningTableId by remember { mutableStateOf<Int?>(null) }
    var notes by remember { mutableStateOf("") }
    var paymentReference by remember { mutableStateOf("") }
    var proofUri by rememberSaveable { mutableStateOf<Uri?>(null) }
    val context = androidx.compose.ui.platform.LocalContext.current
    val calendar = remember { Calendar.getInstance(java.util.TimeZone.getTimeZone("Asia/Manila")) }
    val dateFormat = remember { SimpleDateFormat("yyyy-MM-dd HH:mm", Locale.US).apply { timeZone = java.util.TimeZone.getTimeZone("Asia/Manila") } }
    val proofPicker = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        uri?.let { proofUri = it }
    }
    val cartSheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)
    val notificationSheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)
    val categories = remember(vm.products) { listOf("All") + vm.products.mapNotNull { it.category }.distinct() }
    val filtered = remember(vm.products, category, query) {
        vm.products.filter { product ->
            (category == "All" || product.category == category) &&
                (query.isBlank() || product.name.contains(query, ignoreCase = true) || product.description.orEmpty().contains(query, ignoreCase = true))
        }
    }
    val productsById = remember(vm.products) { vm.products.associateBy(Product::id) }
    val cartProducts = remember(vm.products, vm.cart) { vm.products.filter { it.id in vm.cart } }
    val cartItemCount = vm.cart.values.sum()
    val cartTotal = vm.cart.entries.sumOf { (productId, quantity) -> productsById[productId]?.price?.times(quantity) ?: 0.0 }
    val decisionOrders = remember(vm.orders) { vm.orders.filter { it.payment_status in setOf("paid", "rejected") } }
    val decisionKeys = remember(decisionOrders) { decisionOrders.map { "${it.id}:${it.payment_status}" }.toSet() }
    val unreadNotificationCount = decisionKeys.count { it !in vm.readOrderNotificationKeys }
    val canPay = payment == "cash" || (payment == "paymongo" && vm.payMongoEnabled) || (payment == "gcash" && paymentReference.length == 13 && proofUri != null)

    LaunchedEffect(vm.cart.isEmpty()) {
        if (vm.cart.isEmpty()) checkingOut = false
    }
    LaunchedEffect(notificationsOpen, decisionKeys) {
        if (notificationsOpen) vm.markOrderNotificationsRead(decisionKeys)
    }

    Box(Modifier.fillMaxSize()) {
        LazyVerticalGrid(
            columns = GridCells.Fixed(2),
            modifier = Modifier.fillMaxSize(),
            contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 18.dp, bottom = if (cartItemCount > 0) 100.dp else 24.dp),
            horizontalArrangement = Arrangement.spacedBy(12.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            item(key = "menu-header", span = { GridItemSpan(maxLineSpan) }) {
                Column {
                    ScreenHeader(
                        eyebrow = vm.user?.name?.substringBefore(' ')?.takeIf { it.isNotBlank() }?.let { "Hi, $it" } ?: "Kermit's menu",
                        title = "What are you craving?",
                    ) {
                        BadgedBox(
                            badge = {
                                if (unreadNotificationCount > 0) {
                                    Badge(modifier = Modifier.clearAndSetSemantics { }, containerColor = KColors.Lime, contentColor = KColors.Ink) {
                                        Text(if (unreadNotificationCount > 99) "99+" else unreadNotificationCount.toString())
                                    }
                                }
                            },
                        ) {
                            FilledIconButton(
                                onClick = { notificationsOpen = true },
                                modifier = Modifier.semantics {
                                    contentDescription = if (unreadNotificationCount == 0) "Order notifications" else "Order notifications, $unreadNotificationCount unread"
                                },
                                colors = IconButtonDefaults.filledIconButtonColors(containerColor = Color.White, contentColor = KColors.Ink),
                            ) { Icon(Icons.Default.Notifications, contentDescription = null) }
                        }
                        Spacer(Modifier.width(8.dp))
                        BadgedBox(
                            badge = {
                                if (cartItemCount > 0) {
                                    Badge(modifier = Modifier.clearAndSetSemantics { }, containerColor = KColors.Lime, contentColor = KColors.Ink) {
                                        Text(if (cartItemCount > 99) "99+" else cartItemCount.toString())
                                    }
                                }
                            },
                        ) {
                            FilledIconButton(
                                onClick = { cartOpen = true },
                                modifier = Modifier.semantics {
                                    contentDescription = when (cartItemCount) {
                                        0 -> "Cart, empty"
                                        1 -> "Cart, 1 item"
                                        else -> "Cart, $cartItemCount items"
                                    }
                                },
                                colors = IconButtonDefaults.filledIconButtonColors(containerColor = KColors.Ink, contentColor = Color.White),
                            ) { Icon(Icons.Default.ShoppingCart, contentDescription = null) }
                        }
                    }
                    Spacer(Modifier.height(16.dp))
                    OutlinedTextField(
                        query,
                        { query = it },
                        placeholder = { Text("Search dishes and drinks") },
                        leadingIcon = { Icon(Icons.Default.Search, null, tint = KColors.Muted) },
                        trailingIcon = if (query.isNotEmpty()) {
                            { IconButton(onClick = { query = "" }) { Icon(Icons.Default.Close, "Clear search", tint = KColors.Muted) } }
                        } else null,
                        singleLine = true,
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedContainerColor = Color.White,
                            unfocusedContainerColor = Color.White,
                            focusedBorderColor = KColors.Olive,
                            unfocusedBorderColor = KColors.Line,
                            cursorColor = KColors.Olive,
                        ),
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(50),
                    )
                    Spacer(Modifier.height(12.dp))
                    Row(Modifier.horizontalScroll(rememberScrollState())) {
                        categories.forEach { value -> KChip(selected = category == value, label = value, onClick = { category = value }) }
                    }
                }
            }
            if (filtered.isEmpty()) {
                item(key = "menu-empty", span = { GridItemSpan(maxLineSpan) }) {
                    EmptyState(
                        Icons.Default.Search,
                        if (vm.products.isEmpty()) "The menu is not available yet" else "No dishes match \"$query\"",
                        if (vm.products.isEmpty()) "Pull down to refresh, or check your internet connection." else "Try another name or choose a different category.",
                    )
                }
            }
            filtered.groupBy { it.category ?: "Favorites" }.forEach { (categoryName, categoryProducts) ->
                item(key = "category-$categoryName", span = { GridItemSpan(maxLineSpan) }) {
                    Row(Modifier.fillMaxWidth().padding(top = 8.dp), verticalAlignment = Alignment.Bottom) {
                        Text(categoryName, fontSize = 19.sp, fontWeight = FontWeight.Black, modifier = Modifier.weight(1f).semantics { heading() })
                        Text("${categoryProducts.size} ${if (categoryProducts.size == 1) "item" else "items"}", color = KColors.Muted, fontSize = 12.sp)
                    }
                }
                items(categoryProducts, key = Product::id) { product ->
                    val quantity = vm.cart[product.id] ?: 0
                    MenuProductCard(product, quantity) { target -> if (target > quantity) vm.add(product) else vm.remove(product) }
                }
            }
        }

        AnimatedVisibility(
            visible = cartItemCount > 0,
            modifier = Modifier.align(Alignment.BottomCenter),
            enter = slideInVertically { it } + fadeIn(),
            exit = slideOutVertically { it } + fadeOut(),
        ) {
            Surface(
                onClick = { cartOpen = true },
                shape = RoundedCornerShape(20.dp),
                color = KColors.Ink,
                shadowElevation = 10.dp,
                modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 14.dp),
            ) {
                Row(Modifier.padding(horizontal = 14.dp, vertical = 12.dp), verticalAlignment = Alignment.CenterVertically) {
                    Box(Modifier.size(36.dp).background(KColors.Lime, androidx.compose.foundation.shape.CircleShape), contentAlignment = Alignment.Center) {
                        Text(if (cartItemCount > 99) "99+" else cartItemCount.toString(), color = KColors.Ink, fontSize = 13.sp, fontWeight = FontWeight.Black)
                    }
                    Column(Modifier.weight(1f).padding(horizontal = 12.dp)) {
                        Text("View cart", color = Color.White, fontWeight = FontWeight.Bold)
                        Text("$cartItemCount ${if (cartItemCount == 1) "item" else "items"} ready to order", color = Color(0xFFC5C8BF), fontSize = 12.sp)
                    }
                    Text(money(cartTotal), color = KColors.Lime, fontSize = 17.sp, fontWeight = FontWeight.Black)
                }
            }
        }
    }

    if (notificationsOpen) {
        ModalBottomSheet(
            onDismissRequest = { notificationsOpen = false },
            sheetState = notificationSheetState,
            containerColor = KColors.Canvas,
        ) {
            LazyColumn(
                modifier = Modifier.fillMaxWidth().fillMaxHeight(0.78f),
                contentPadding = PaddingValues(start = 20.dp, end = 20.dp, bottom = 32.dp),
            ) {
                item(key = "notification-title") {
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                        Column {
                            Text("NOTIFICATIONS", color = KColors.Olive, fontSize = 10.sp, letterSpacing = 1.4.sp, fontWeight = FontWeight.ExtraBold)
                            Text("Order updates", fontSize = 25.sp, fontWeight = FontWeight.Bold)
                        }
                        TextButton(onClick = { notificationsOpen = false }) { Text("Close") }
                    }
                    Text("Accepted and rejected orders appear here.", color = KColors.Muted, fontSize = 13.sp, modifier = Modifier.padding(top = 4.dp, bottom = 14.dp))
                }
                if (decisionOrders.isEmpty()) {
                    item(key = "empty-notifications") {
                        Surface(Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), color = Color.White) {
                            Column(Modifier.fillMaxWidth().padding(28.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                                Icon(Icons.Default.Notifications, contentDescription = null, tint = KColors.Olive, modifier = Modifier.size(36.dp))
                                Text("No order notifications yet", fontWeight = FontWeight.Bold, modifier = Modifier.padding(top = 10.dp))
                                Text("You will see an update when the cashier accepts or rejects an order.", color = KColors.Muted, fontSize = 13.sp, lineHeight = 18.sp, modifier = Modifier.padding(top = 5.dp))
                            }
                        }
                    }
                } else {
                    items(decisionOrders, key = { order -> "notification-${order.id}:${order.payment_status}" }) { order ->
                        val accepted = order.payment_status == "paid"
                        Surface(
                            onClick = { notificationsOpen = false; onNotificationOrder(order.id) },
                            modifier = Modifier.fillMaxWidth().padding(bottom = 10.dp),
                            shape = RoundedCornerShape(11.dp),
                            color = Color.White,
                            border = androidx.compose.foundation.BorderStroke(1.dp, KColors.Line),
                        ) {
                            Row(Modifier.padding(15.dp), verticalAlignment = Alignment.CenterVertically) {
                                Surface(shape = androidx.compose.foundation.shape.CircleShape, color = if (accepted) KColors.SuccessSoft else KColors.DangerSoft, modifier = Modifier.size(44.dp)) {
                                    Box(contentAlignment = Alignment.Center) {
                                        Icon(if (accepted) Icons.Default.CheckCircle else Icons.Default.Close, contentDescription = null, tint = if (accepted) KColors.Success else KColors.Danger, modifier = Modifier.size(23.dp))
                                    }
                                }
                                Column(Modifier.weight(1f).padding(start = 12.dp)) {
                                    Text(if (accepted) "Order accepted" else "Order rejected", fontWeight = FontWeight.Bold, fontSize = 16.sp)
                                    Text("Order #${order.id} - ${money(order.total_due)}", color = Color(0xFF555B52), fontSize = 13.sp, modifier = Modifier.padding(top = 4.dp))
                                    order.created_at?.let { Text(receiptDate(it), color = Color(0xFF858A81), fontSize = 11.sp, modifier = Modifier.padding(top = 4.dp)) }
                                }
                                Icon(Icons.Default.ChevronRight, contentDescription = null, tint = Color(0xFF777D72))
                            }
                        }
                    }
                }
            }
        }
    }

    if (cartOpen) {
        ModalBottomSheet(
            onDismissRequest = { cartOpen = false },
            sheetState = cartSheetState,
            containerColor = KColors.Canvas,
        ) {
            LazyColumn(
                modifier = Modifier.fillMaxWidth().fillMaxHeight(0.9f),
                contentPadding = PaddingValues(start = 20.dp, end = 20.dp, bottom = 32.dp),
            ) {
                item(key = "cart-title") {
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                        Column {
                            Text("YOUR CART", color = KColors.Olive, fontSize = 10.sp, letterSpacing = 1.4.sp, fontWeight = FontWeight.ExtraBold)
                            AnimatedContent(checkingOut, transitionSpec = { fadeIn(tween(180)) togetherWith fadeOut(tween(90)) }, label = "checkoutHeading") { checkout ->
                                Text(if (checkout) "Checkout" else "Your order", fontSize = 25.sp, fontWeight = FontWeight.Bold)
                            }
                        }
                        TextButton(onClick = { cartOpen = false }) { Text("Close") }
                    }
                    Spacer(Modifier.height(10.dp))
                }

                if (vm.cart.isEmpty()) {
                    item(key = "empty-cart") {
                        Surface(Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp), color = Color.White) {
                            Column(Modifier.fillMaxWidth().padding(28.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                                Icon(Icons.Default.ShoppingCart, contentDescription = null, tint = KColors.Olive, modifier = Modifier.size(36.dp))
                                Text("Your cart is empty", fontWeight = FontWeight.Bold, modifier = Modifier.padding(top = 10.dp))
                                Text("Add an item from the menu to start an order.", color = KColors.Muted, fontSize = 13.sp, modifier = Modifier.padding(top = 5.dp))
                                OutlinedButton(onClick = { cartOpen = false }, modifier = Modifier.padding(top = 16.dp)) { Text("Browse menu") }
                            }
                        }
                    }
                } else if (!checkingOut) {
                    items(cartProducts, key = { product -> "cart-${product.id}" }) { product ->
                        val quantity = vm.cart[product.id] ?: 0
                        Surface(Modifier.fillMaxWidth().padding(bottom = 10.dp), shape = RoundedCornerShape(12.dp), color = Color.White) {
                            Row(Modifier.fillMaxWidth().padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                                Column(Modifier.weight(1f)) {
                                    Text(product.name, fontWeight = FontWeight.ExtraBold)
                                    Text("${money(product.price)} each · ${money(product.price * quantity)}", color = KColors.Muted, fontSize = 12.sp, modifier = Modifier.padding(top = 3.dp))
                                }
                                IconButton(
                                    onClick = { vm.remove(product) },
                                    modifier = Modifier.semantics { contentDescription = "Decrease ${product.name} quantity" },
                                ) { Icon(Icons.Default.Remove, contentDescription = null) }
                                Text(quantity.toString(), fontWeight = FontWeight.Bold)
                                IconButton(
                                    onClick = { vm.add(product) },
                                    enabled = quantity < product.stock,
                                    modifier = Modifier.semantics { contentDescription = "Increase ${product.name} quantity" },
                                ) { Icon(Icons.Default.Add, contentDescription = null) }
                            }
                        }
                    }
                    item(key = "cart-summary") {
                        HorizontalDivider(color = KColors.Line, modifier = Modifier.padding(vertical = 6.dp))
                        Row(Modifier.fillMaxWidth().padding(vertical = 10.dp), horizontalArrangement = Arrangement.SpaceBetween) {
                            Text("$cartItemCount ${if (cartItemCount == 1) "item" else "items"}", fontWeight = FontWeight.Bold)
                            CartAmount(cartTotal)
                        }
                        Button(
                            onClick = { checkingOut = true },
                            shape = RoundedCornerShape(50.dp),
                            modifier = Modifier.fillMaxWidth().height(48.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = KColors.Ink, contentColor = Color.White),
                        ) { Text("Place Order", fontWeight = FontWeight.ExtraBold) }
                    }
                } else {
                    item(key = "checkout-form") {
                        TextButton(onClick = { checkingOut = false }, contentPadding = PaddingValues(0.dp)) { Text("← Back to cart") }
                        Text("Reserve a table", fontSize = 23.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(top = 5.dp, bottom = 3.dp))
                        Text("Your selected food is already in the order, so only the table details are needed here.", color = KColors.Muted, fontSize = 12.sp, lineHeight = 18.sp)
                        Row(Modifier.fillMaxWidth().padding(top = 12.dp), horizontalArrangement = Arrangement.SpaceBetween) {
                            Text("Order total", fontWeight = FontWeight.Bold)
                            CartAmount(cartTotal)
                        }
                        Spacer(Modifier.height(14.dp))
                        OutlinedTextField(phone, { phone = it.filter(Char::isDigit).take(11) }, label = { Text("Phone number") }, supportingText = { Text("11 digits starting with 09") }, singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number), modifier = Modifier.fillMaxWidth(), colors = loginFieldColors(), shape = RoundedCornerShape(11.dp))
                        Spacer(Modifier.height(8.dp))
                        DateTimePickerField(date, "Date and time", "Choose your schedule", "Open 8 AM-11 PM (Philippine time). Last arrival: 10 PM.") { showDateTimePicker(context, calendar, dateFormat) { date = it } }
                        ReservationSlotChoices(vm, date, "table", tableSize.toIntOrNull() ?: 1, tableId = diningTableId) { date = it }
                        Spacer(Modifier.height(8.dp))
                        Row(Modifier.horizontalScroll(rememberScrollState())) {
                            vm.tableSizes.forEach { value ->
                                KChip(selected = tableSize == value, label = "Up to $value guests · ${money(vm.tableFees[value] ?: 0.0)}", onClick = { tableSize = value })
                            }
                        }
                        TableChoice(vm, tableSize.toIntOrNull() ?: 1, diningTableId) { diningTableId = it }
                        Spacer(Modifier.height(8.dp))
                        OutlinedTextField(notes, { notes = it.take(2000) }, label = { Text("Additional notes (optional)") }, minLines = 2, modifier = Modifier.fillMaxWidth(), colors = loginFieldColors(), shape = RoundedCornerShape(11.dp))
                        Spacer(Modifier.height(10.dp))
                        Row(Modifier.horizontalScroll(rememberScrollState()), verticalAlignment = Alignment.CenterVertically) {
                            Text("Payment:")
                            Spacer(Modifier.width(8.dp))
                            KChip(selected = payment == "cash", label = "Cash", onClick = { setPayment("cash") })
                            Spacer(Modifier.width(6.dp))
                            KChip(selected = payment == "gcash", label = "GCash", onClick = { setPayment("gcash") })
                            if (vm.payMongoEnabled) {
                                Spacer(Modifier.width(6.dp))
                                KChip(selected = payment == "paymongo", label = "PayMongo", onClick = { setPayment("paymongo") })
                            }
                        }
                        if (payment == "paymongo") PayMongoNote()
                        if (payment == "gcash") {
                            vm.gcashQrUrl?.let { AsyncImage(it, "GCash QR code", Modifier.fillMaxWidth().height(150.dp).padding(vertical = 8.dp), contentScale = ContentScale.Inside) }
                            OutlinedTextField(paymentReference, { paymentReference = it.filter(Char::isDigit).take(13) }, label = { Text("13-digit GCash reference") }, singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number), modifier = Modifier.fillMaxWidth(), colors = loginFieldColors(), shape = RoundedCornerShape(11.dp))
                            Spacer(Modifier.height(8.dp))
                            PaymentProofAttachment(
                                proofUri = proofUri,
                                onChoose = { proofPicker.launch("image/*") },
                                onRemove = { proofUri = null },
                            )
                        }
                        Spacer(Modifier.height(12.dp))
                        Button(
                            onClick = {
                                vm.placeOrder(context, CheckoutDetails(phone, date, tableSize, payment, paymentReference, notes, proofUri, diningTableId)) { order ->
                                    if (order != null) {
                                        paymentReference = ""
                                        proofUri = null
                                        checkingOut = false
                                        cartOpen = false
                                        onOrderSubmitted(order)
                                    }
                                }
                            },
                            enabled = !vm.busy && phone.matches(Regex("09\\d{9}")) && date.isNotBlank() && canPay,
                            shape = RoundedCornerShape(11.dp),
                            modifier = Modifier.fillMaxWidth().height(50.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = KColors.Ink, contentColor = Color.White),
                        ) { Text(if (vm.busy) "Submitting..." else if (payment == "paymongo") "Continue to PayMongo" else "Confirm payment & view receipt", fontWeight = FontWeight.Bold) }
                    }
                }
            }
        }
    }
}

@Composable
private fun PayMongoNote() {
    Text(
        "You will be sent to PayMongo's secure checkout page to pay. Your receipt shows Paid once PayMongo confirms the payment.",
        color = KColors.Muted,
        fontSize = 12.sp,
        lineHeight = 18.sp,
        modifier = Modifier.padding(top = 6.dp),
    )
}

@Composable
private fun PaymentProofAttachment(
    proofUri: Uri?,
    onChoose: () -> Unit,
    onRemove: () -> Unit,
) {
    if (proofUri == null) {
        OutlinedButton(onClick = onChoose, modifier = Modifier.fillMaxWidth()) {
            Text("Attach payment proof")
        }
        return
    }

    Surface(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(12.dp),
        color = Color.White,
        border = androidx.compose.foundation.BorderStroke(1.dp, KColors.Line),
    ) {
        Column(Modifier.fillMaxWidth().padding(12.dp)) {
            Text("Attached payment proof", fontWeight = FontWeight.ExtraBold)
            Text(
                "Check the image before submitting. You can replace or remove it if it is incorrect.",
                color = KColors.Muted,
                fontSize = 12.sp,
                lineHeight = 17.sp,
                modifier = Modifier.padding(top = 3.dp, bottom = 10.dp),
            )
            AsyncImage(
                model = proofUri,
                contentDescription = "Selected GCash payment proof preview",
                modifier = Modifier
                    .fillMaxWidth()
                    .height(220.dp)
                    .clip(RoundedCornerShape(9.dp))
                    .background(Color(0xFFF0F1EC)),
                contentScale = ContentScale.Fit,
            )
            Row(
                modifier = Modifier.fillMaxWidth().padding(top = 10.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                OutlinedButton(onClick = onChoose, modifier = Modifier.weight(1f)) {
                    Text("Replace image")
                }
                Spacer(Modifier.width(8.dp))
                TextButton(onClick = onRemove) {
                    Text("Remove", color = MaterialTheme.colorScheme.error)
                }
            }
        }
    }
}

@Composable
private fun MenuProductCard(product: Product, quantity: Int, onQuantity: (Int) -> Unit) {
    // Same as the web menu: stock already in the cart is no longer available.
    val remaining = (product.stock - quantity).coerceAtLeast(0)
    val lowStock = remaining < 10
    val selected = quantity > 0
    KCard(
        Modifier.fillMaxWidth(),
        contentPadding = 0.dp,
        border = androidx.compose.foundation.BorderStroke(if (selected) 2.dp else 1.dp, if (selected) KColors.Lime else KColors.Line),
    ) {
        ProductImage(product, Modifier.fillMaxWidth())
        Column(Modifier.padding(12.dp)) {
            Text(product.name, fontSize = 14.sp, lineHeight = 18.sp, fontWeight = FontWeight.ExtraBold, maxLines = 2, minLines = 2, overflow = TextOverflow.Ellipsis)
            Text(product.description.orEmpty(), color = KColors.Muted, fontSize = 11.sp, lineHeight = 15.sp, maxLines = 2, minLines = 2, overflow = TextOverflow.Ellipsis, modifier = Modifier.padding(top = 3.dp))
            Text(money(product.price), fontSize = 15.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(top = 8.dp))
            Text(
                when {
                    product.stock <= 0 -> "Sold out"
                    lowStock -> "Only $remaining left"
                    else -> "$remaining available"
                },
                color = if (lowStock) KColors.Danger else KColors.Muted,
                fontSize = 11.sp,
                fontWeight = if (lowStock) FontWeight.ExtraBold else FontWeight.SemiBold,
            )
            Spacer(Modifier.height(10.dp))
            QuantityStepper(product.name, quantity, canIncrease = quantity < product.stock, onChange = onQuantity, modifier = Modifier.fillMaxWidth())
        }
    }
}

@Composable
private fun CustomerHistoryScreen(vm: AppViewModel, onOrder: (Int) -> Unit, onReservation: (Int) -> Unit, onReserve: () -> Unit) {
    var activityTab by rememberSaveable { mutableIntStateOf(0) }
    val activeReservations = remember(vm.reservations) { vm.reservations.count { it.status in listOf("pending", "confirmed") } }
    val paidOrders = remember(vm.orders) { vm.orders.count { it.payment_status == "paid" } }
    val reservationGroups = remember(vm.reservations) {
        vm.reservations
            .sortedByDescending { reservationHistorySortKey(it.reservation_at) }
            .groupBy { reservationHistoryDay(it.reservation_at) }
    }
    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 18.dp, bottom = 28.dp)) {
        item(key = "history-head") {
            ScreenHeader("My activity", "History", subtitle = "Your reservations and purchases.") {
                Button(
                    onClick = onReserve,
                    shape = RoundedCornerShape(50),
                    colors = ButtonDefaults.buttonColors(containerColor = KColors.Ink, contentColor = Color.White),
                    contentPadding = PaddingValues(horizontal = 14.dp, vertical = 8.dp),
                ) {
                    Icon(Icons.Default.Add, contentDescription = null, modifier = Modifier.size(16.dp))
                    Spacer(Modifier.width(4.dp))
                    Text("Reserve", fontSize = 13.sp, fontWeight = FontWeight.Bold)
                }
            }
            Row(Modifier.fillMaxWidth().padding(top = 18.dp), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                HistoryMetric("Reservations", vm.reservations.size, "$activeReservations active", Modifier.weight(1f), dark = true)
                HistoryMetric("Purchases", vm.orders.size, "$paidOrders paid", Modifier.weight(1f))
            }
            Row(Modifier.fillMaxWidth().padding(top = 20.dp).background(Color(0xFFE9EBE3), RoundedCornerShape(50)).padding(4.dp)) {
                listOf("Reservations" to vm.reservations.size, "Purchases" to vm.orders.size).forEachIndexed { index, (label, count) ->
                    val chosen = activityTab == index
                    Surface(
                        onClick = { activityTab = index },
                        shape = RoundedCornerShape(50),
                        color = if (chosen) Color.White else Color.Transparent,
                        shadowElevation = if (chosen) 1.dp else 0.dp,
                        modifier = Modifier.weight(1f).semantics { selected = chosen },
                    ) {
                        Text("$label · $count", color = if (chosen) KColors.Ink else KColors.Muted, fontSize = 13.sp, fontWeight = FontWeight.Bold, textAlign = TextAlign.Center, modifier = Modifier.padding(vertical = 10.dp))
                    }
                }
            }
            Spacer(Modifier.height(14.dp))
        }
        if (activityTab == 0) {
            if (vm.reservations.isEmpty()) {
                item(key = "empty-reservations") {
                    EmptyState(Icons.Default.CalendarMonth, "No reservations yet", "Book the Exclusive Venue from Reserve, or add a table request when you order.") {
                        SecondaryButton("Make a reservation", onClick = onReserve)
                    }
                }
            }
            reservationGroups.forEach { (date, reservations) ->
                item(key = "reservation-date-$date") {
                    Eyebrow(date, color = KColors.Muted, modifier = Modifier.padding(top = 8.dp, bottom = 8.dp))
                }
                items(reservations, key = { "reservation-${it.id}" }) { reservation ->
                    ActivityCard(
                        title = reservation.reference,
                        kind = "Reservation",
                        status = reservation.status,
                        details = listOf(
                            "Schedule" to reservationScheduleLabel(reservation),
                            "Party" to if (reservation.type == "table") "${reservation.table_size} guests" else "${reservation.guests} guests · Exclusive Venue",
                            "Total" to money(reservation.total_amount),
                        ),
                        onClick = { onReservation(reservation.id) },
                    )
                }
            }
        } else {
            if (vm.orders.isEmpty()) {
                item(key = "empty-orders") {
                    EmptyState(Icons.AutoMirrored.Filled.ReceiptLong, "No purchases yet", "Your menu orders and receipts will appear here.")
                }
            }
            items(vm.orders, key = { "order-${it.id}" }) { order ->
                ActivityCard(
                    title = "Order #${order.id}",
                    kind = "Purchase",
                    status = order.payment_status,
                    details = listOf("Date" to receiptDate(order.created_at), "Payment" to paymentMethodLabel(order.payment_method), "Total due" to money(order.total_due)),
                    actionLabel = if (order.payment_status.equals("rejected", ignoreCase = true)) "View order" else "View receipt",
                    onClick = { onOrder(order.id) },
                )
            }
        }
    }
}

@Composable
private fun HistoryMetric(label: String, value: Int, detail: String, modifier: Modifier = Modifier, dark: Boolean = false) {
    Surface(
        modifier,
        shape = RoundedCornerShape(18.dp),
        color = if (dark) KColors.Ink else Color.White,
        border = if (dark) null else androidx.compose.foundation.BorderStroke(1.dp, KColors.Line),
    ) {
        Column(Modifier.padding(16.dp)) {
            Text(label, color = if (dark) Color(0xFFC5C8BF) else KColors.Muted, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
            Text(value.toString(), color = if (dark) Color.White else KColors.Ink, fontSize = 28.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(top = 2.dp))
            Text(detail, color = if (dark) KColors.Lime else KColors.Olive, fontSize = 12.sp, fontWeight = FontWeight.Bold)
        }
    }
}

@Composable
private fun ActivityCard(title: String, kind: String, status: String, details: List<Pair<String, String>>, actionLabel: String = "View details", onClick: () -> Unit) {
    KCard(Modifier.fillMaxWidth().padding(bottom = 10.dp), onClick = onClick) {
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            Box(Modifier.size(42.dp).background(KColors.LimeSoft, RoundedCornerShape(12.dp)), contentAlignment = Alignment.Center) {
                Icon(if (kind == "Reservation") Icons.Default.CalendarMonth else Icons.AutoMirrored.Filled.ReceiptLong, contentDescription = null, tint = KColors.Olive, modifier = Modifier.size(21.dp))
            }
            Column(Modifier.weight(1f).padding(horizontal = 12.dp)) {
                Text(kind, color = KColors.Muted, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                Text(title, fontSize = 16.sp, fontWeight = FontWeight.Bold, maxLines = 1, overflow = TextOverflow.Ellipsis)
            }
            StatusPill(status)
        }
        HorizontalDivider(Modifier.padding(vertical = 12.dp), color = KColors.Line)
        details.forEach { (label, value) ->
            Row(Modifier.fillMaxWidth().padding(vertical = 3.dp), verticalAlignment = Alignment.CenterVertically) {
                Text(label, color = KColors.Muted, fontSize = 12.sp)
                Text(value, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, maxLines = 1, overflow = TextOverflow.Ellipsis, textAlign = TextAlign.End, modifier = Modifier.weight(1f).padding(start = 12.dp))
            }
        }
        Row(Modifier.align(Alignment.End).padding(top = 8.dp), verticalAlignment = Alignment.CenterVertically) {
            Text(actionLabel, color = KColors.Olive, fontSize = 13.sp, fontWeight = FontWeight.ExtraBold)
            Icon(Icons.Default.ChevronRight, contentDescription = null, tint = KColors.Olive, modifier = Modifier.size(18.dp))
        }
    }
}

@Composable
private fun OrderReceiptDialog(
    order: Order,
    customerName: String,
    wasJustSubmitted: Boolean,
    onPayMongo: (() -> Unit)?,
    close: () -> Unit,
) {
    val context = LocalContext.current
    val isPaid = order.payment_status.equals("paid", ignoreCase = true)
    val isRejected = order.payment_status.equals("rejected", ignoreCase = true)
    val paymentLabel = paymentMethodLabel(order.payment_method)
    val receiptLabel = when {
        isPaid -> "OFFICIAL RECEIPT"
        isRejected -> "ORDER REJECTED"
        else -> "ORDER RECEIPT"
    }
    val statusLabel = when {
        isPaid -> "PAID"
        isRejected -> "REJECTED"
        else -> "PAYMENT PENDING"
    }
    val paymentMessage = when {
        isPaid -> "Payment confirmed."
        isRejected -> "This order was rejected. No payment is required."
        order.payment_method == "gcash" -> "Payment submitted — awaiting verification."
        order.payment_method == "paymongo" -> "Complete payment on PayMongo. This receipt shows Paid once PayMongo confirms it."
        else -> "Payment due at the counter."
    }

    Dialog(
        onDismissRequest = close,
        properties = DialogProperties(usePlatformDefaultWidth = false),
    ) {
        Surface(
            modifier = Modifier.fillMaxWidth().fillMaxHeight(0.92f).padding(horizontal = 16.dp).widthIn(max = 560.dp),
            shape = RoundedCornerShape(18.dp),
            color = KColors.Canvas,
            shadowElevation = 12.dp,
        ) {
            Column(Modifier.fillMaxSize()) {
                LazyColumn(
                    modifier = Modifier.fillMaxWidth().weight(1f),
                    contentPadding = PaddingValues(20.dp),
                ) {
                    item(key = "receipt-header") {
                        if (wasJustSubmitted) {
                            Surface(
                                modifier = Modifier.fillMaxWidth().padding(bottom = 14.dp),
                                shape = RoundedCornerShape(10.dp),
                                color = Color(0xFFE8F4E9),
                            ) {
                                Text(
                                    "Order and table request submitted.",
                                    color = KColors.Success,
                                    fontWeight = FontWeight.ExtraBold,
                                    modifier = Modifier.padding(12.dp),
                                )
                            }
                        }
                        Text("KERMIT'S", color = KColors.Olive, fontSize = 11.sp, letterSpacing = 1.6.sp, fontWeight = FontWeight.Black)
                        Text(
                            receiptLabel,
                            fontSize = 25.sp,
                            fontWeight = FontWeight.Black,
                            modifier = Modifier.padding(top = 4.dp).semantics { heading() },
                        )
                        Text("#${String.format(Locale.US, "%06d", order.id)}", color = KColors.Muted, fontWeight = FontWeight.Bold, modifier = Modifier.padding(top = 2.dp))
                        Surface(
                            modifier = Modifier.fillMaxWidth().padding(top = 14.dp),
                            shape = RoundedCornerShape(9.dp),
                            color = when {
                                isPaid -> KColors.SuccessSoft
                                isRejected -> KColors.DangerSoft
                                else -> KColors.WarningSoft
                            },
                        ) {
                            Column(Modifier.padding(12.dp)) {
                                Text(statusLabel, color = if (isRejected) KColors.Danger else Color.Unspecified, fontSize = 11.sp, fontWeight = FontWeight.Black)
                                Text(paymentMessage, fontSize = 13.sp, modifier = Modifier.padding(top = 3.dp))
                            }
                        }
                        Column(Modifier.fillMaxWidth().padding(vertical = 14.dp)) {
                            ReceiptLine("Date", receiptDate(order.created_at))
                            ReceiptLine("Customer", customerName.ifBlank { "Customer" })
                            ReceiptLine("Payment", paymentLabel)
                            ReceiptLine("Payment status", when { isPaid -> "Paid"; isRejected -> "Rejected"; else -> "Pending" })
                            order.payment_reference?.let { ReceiptLine("GCash reference", it) }
                        }
                        Text("ITEMS", color = KColors.Olive, fontSize = 10.sp, letterSpacing = 1.2.sp, fontWeight = FontWeight.Black)
                        HorizontalDivider(color = KColors.Line, modifier = Modifier.padding(top = 8.dp))
                    }

                    items(order.items, key = { item -> "receipt-item-${item.product_id}" }) { item ->
                        Row(Modifier.fillMaxWidth().padding(vertical = 10.dp), verticalAlignment = Alignment.Top) {
                            Column(Modifier.weight(1f).padding(end = 12.dp)) {
                                Text(item.name, fontWeight = FontWeight.ExtraBold)
                                Text("${item.quantity} × ${money(item.unit_price)}", color = KColors.Muted, fontSize = 12.sp, modifier = Modifier.padding(top = 2.dp))
                            }
                            Text(money(item.subtotal), fontWeight = FontWeight.Bold)
                        }
                        HorizontalDivider(color = KColors.Line)
                    }

                    item(key = "receipt-totals") {
                        Column(Modifier.fillMaxWidth().padding(top = 12.dp)) {
                            ReceiptLine("Food subtotal", money(order.total))
                            order.reservation?.let { reservation ->
                                ReceiptLine("Reservation fee", money(reservation.total_amount))
                            }
                            HorizontalDivider(color = KColors.Line, modifier = Modifier.padding(vertical = 8.dp))
                            ReceiptLine(when { isPaid -> "Total paid"; isRejected -> "Order total"; else -> "Total due" }, money(order.total_due), emphasized = true)
                            order.cash_received?.let { ReceiptLine("Cash received", money(it)) }
                            order.change_due?.let { ReceiptLine("Change", money(it)) }
                        }
                    }

                    order.reservation?.let { reservation ->
                        item(key = "receipt-reservation") {
                            Surface(
                                modifier = Modifier.fillMaxWidth().padding(top = 16.dp),
                                shape = RoundedCornerShape(11.dp),
                                color = Color.White,
                                border = androidx.compose.foundation.BorderStroke(1.dp, KColors.Line),
                            ) {
                                Column(Modifier.padding(13.dp)) {
                                    Text("TABLE REQUEST", color = KColors.Olive, fontSize = 10.sp, letterSpacing = 1.1.sp, fontWeight = FontWeight.Black)
                                    ReceiptLine("Reference", reservation.reference)
                                    ReceiptLine("Schedule", reservationScheduleLabel(reservation))
                                    reservation.hold_expires_at?.let { ReceiptLine("Approval deadline", receiptDate(it)) }
                                    ReceiptLine("Party", "${reservation.table_size ?: reservation.guests ?: 0} guests")
                                    ReceiptLine("Status", reservation.status.replaceFirstChar { it.uppercase() })
                                }
                            }
                        }
                    }

                    item(key = "receipt-note") {
                        Text(
                            when {
                                isPaid -> "Thank you for your purchase!"
                                isRejected -> "This order was rejected and no payment is due."
                                else -> "This confirms your order request. It becomes an official receipt after payment is verified."
                            },
                            color = KColors.Muted,
                            fontSize = 12.sp,
                            lineHeight = 18.sp,
                            modifier = Modifier.fillMaxWidth().padding(top = 18.dp, bottom = 4.dp),
                        )
                    }
                }
                HorizontalDivider(color = KColors.Line)
                onPayMongo?.let { pay ->
                    Button(
                        onClick = pay,
                        modifier = Modifier.fillMaxWidth().padding(start = 16.dp, end = 16.dp, top = 16.dp).height(50.dp),
                        shape = RoundedCornerShape(11.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = KColors.Olive, contentColor = Color.White),
                    ) { Text("Pay with PayMongo", fontWeight = FontWeight.ExtraBold) }
                }
                Row(
                    modifier = Modifier.fillMaxWidth().padding(16.dp),
                    horizontalArrangement = Arrangement.spacedBy(10.dp),
                ) {
                    OutlinedButton(
                        onClick = { printOrderReceipt(context, order, customerName) },
                        modifier = Modifier.weight(1f).height(50.dp),
                        shape = RoundedCornerShape(11.dp),
                    ) { Text(if (isRejected) "Download order" else "Download receipt", fontWeight = FontWeight.ExtraBold) }
                    Button(
                        onClick = close,
                        modifier = Modifier.weight(1f).height(50.dp),
                        shape = RoundedCornerShape(11.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = KColors.Ink, contentColor = Color.White),
                    ) { Text("Close receipt", fontWeight = FontWeight.ExtraBold) }
                }
            }
        }
    }
}

private fun printOrderReceipt(context: Context, order: Order, customerName: String) {
    val activity = context.findActivity() ?: return
    val webView = WebView(activity).apply {
        alpha = 0.01f
        webViewClient = object : WebViewClient() {
            override fun onPageFinished(view: WebView, url: String?) {
                val printManager = activity.getSystemService(Context.PRINT_SERVICE) as? PrintManager ?: return
                val printJob = printManager.print(
                    "Kermits-Receipt-${order.id}",
                    view.createPrintDocumentAdapter("Kermits Receipt #${order.id}"),
                    PrintAttributes.Builder().build(),
                )
                releasePrintedWebView(view, printJob)
            }
        }
    }

    activity.addContentView(webView, ViewGroup.LayoutParams(1, 1))
    webView.loadDataWithBaseURL(null, receiptPrintHtml(order, customerName), "text/html", "UTF-8", null)
}

private fun Context.findActivity(): android.app.Activity? = when (this) {
    is android.app.Activity -> this
    is ContextWrapper -> baseContext.findActivity()
    else -> null
}

private fun releasePrintedWebView(webView: WebView, printJob: PrintJob) {
    val release = object : Runnable {
        override fun run() {
            if (printJob.isCompleted || printJob.isFailed || printJob.isCancelled) {
                (webView.parent as? ViewGroup)?.removeView(webView)
                webView.destroy()
            } else {
                webView.postDelayed(this, 1_000)
            }
        }
    }
    webView.postDelayed(release, 1_000)
}

private fun paymentMethodLabel(method: String): String = when (method) {
    "gcash" -> "GCash"
    "paymongo" -> "PayMongo online"
    else -> "Cash / Pay at counter"
}

private fun receiptPrintHtml(order: Order, customerName: String): String {
    val isPaid = order.payment_status.equals("paid", ignoreCase = true)
    val isRejected = order.payment_status.equals("rejected", ignoreCase = true)
    val paymentLabel = paymentMethodLabel(order.payment_method)
    val receiptLabel = when {
        isPaid -> "OFFICIAL RECEIPT"
        isRejected -> "ORDER REJECTED"
        else -> "ORDER RECEIPT"
    }
    val statusLabel = when {
        isPaid -> "Paid"
        isRejected -> "Rejected"
        else -> "Pending"
    }
    val totalLabel = when {
        isPaid -> "Total paid"
        isRejected -> "Order total"
        else -> "Total due"
    }
    val receiptNote = when {
        isPaid -> "Thank you for your purchase!"
        isRejected -> "This order was rejected and no payment is due."
        else -> "Present this order receipt when paying at the counter."
    }
    val reservationRows = order.reservation?.let { reservation ->
        """
        <div class="line"><span>Reservation</span><b>${reservation.reference.html()}</b></div>
        <div class="line"><span>Schedule</span><b>${reservationScheduleLabel(reservation).html()}</b></div>
        <div class="line"><span>Party</span><b>${reservation.table_size ?: reservation.guests ?: 0} guests</b></div>
        <div class="line"><span>Reservation fee</span><b>${money(reservation.total_amount).html()}</b></div>
        """.trimIndent()
    }.orEmpty()
    val paymentReference = order.payment_reference?.let {
        "<div class=\"line\"><span>GCash reference</span><b>${it.html()}</b></div>"
    }.orEmpty()
    val cashRows = buildString {
        order.cash_received?.let { append("<div class=\"line\"><span>Cash received</span><b>${money(it).html()}</b></div>") }
        order.change_due?.let { append("<div class=\"line\"><span>Change</span><b>${money(it).html()}</b></div>") }
    }
    val items = order.items.joinToString("") { item ->
        "<div class=\"item\"><span><b>${item.name.html()}</b><small>${item.quantity} × ${money(item.unit_price).html()}</small></span><b>${money(item.subtotal).html()}</b></div>"
    }

    return """
        <!doctype html><html><head><meta charset="utf-8"><style>
        @page { size: 80mm auto; margin: 4mm; }
        * { box-sizing: border-box; } body { width: 72mm; margin: 0 auto; color: #171817; font: 12px Arial, sans-serif; }
        h1 { margin: 4px 0; font-size: 21px; } .brand { text-align:center; border-bottom: 1px dashed #9aa096; padding-bottom: 12px; }
        .brand strong { display:block; letter-spacing: 2px; font-size:16px; } .muted, small { color:#667064; } .meta, .totals { padding:12px 0; }
        .line, .item { display:flex; justify-content:space-between; gap:12px; padding:3px 0; } .line b { text-align:right; } .items { border-top:1px dashed #9aa096; border-bottom:1px dashed #9aa096; }
        .item { padding:9px 0; } .item span { display:grid; gap:3px; } .item > b { white-space:nowrap; } .total { border-top:1px solid #222; margin-top:6px; padding-top:8px; font-size:16px; }
        .note { margin:16px 0 0; text-align:center; color:#667064; line-height:1.45; }
        </style></head><body>
        <div class="brand"><strong>KERMIT'S</strong><span>$receiptLabel</span><h1>#${String.format(Locale.US, "%06d", order.id)}</h1></div>
        <div class="meta">
          <div class="line"><span>Date</span><b>${receiptDate(order.created_at).html()}</b></div>
          <div class="line"><span>Customer</span><b>${customerName.ifBlank { "Customer" }.html()}</b></div>
          <div class="line"><span>Payment</span><b>${paymentLabel.html()}</b></div>
          <div class="line"><span>Status</span><b>$statusLabel</b></div>
          $paymentReference
          $reservationRows
        </div>
        <div class="items">$items</div>
        <div class="totals">
          <div class="line"><span>Food subtotal</span><b>${money(order.total).html()}</b></div>
          <div class="line total"><b>$totalLabel</b><b>${money(order.total_due).html()}</b></div>
          $cashRows
        </div>
        <p class="note">$receiptNote</p>
        </body></html>
    """.trimIndent()
}

private fun String.html(): String = replace("&", "&amp;")
    .replace("<", "&lt;")
    .replace(">", "&gt;")
    .replace("\"", "&quot;")
    .replace("'", "&#39;")

@Composable
private fun ReceiptLine(label: String, value: String, emphasized: Boolean = false) {
    Row(
        modifier = Modifier.fillMaxWidth().padding(vertical = if (emphasized) 5.dp else 3.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.Top,
    ) {
        Text(label, color = if (emphasized) KColors.Ink else KColors.Muted, fontSize = if (emphasized) 16.sp else 12.sp, fontWeight = if (emphasized) FontWeight.Black else FontWeight.Medium, modifier = Modifier.weight(1f).padding(end = 12.dp))
        Text(value, fontSize = if (emphasized) 18.sp else 12.sp, fontWeight = if (emphasized) FontWeight.Black else FontWeight.Bold)
    }
}

private const val EXCLUSIVE_MIN_GUESTS = 30
private const val EXCLUSIVE_MAX_GUESTS = 40
private const val RESERVATION_FOOD_MAX = 22

@Composable
private fun ReservationScreen(vm: AppViewModel, setMessage: (String) -> Unit) {
    var phone by remember { mutableStateOf(vm.user?.phone.orEmpty()) }
    var date by remember { mutableStateOf("") }
    var guests by remember { mutableIntStateOf(EXCLUSIVE_MIN_GUESTS) }
    var notes by remember { mutableStateOf("") }
    var foodRequest by remember { mutableStateOf("") }
    var reference by remember { mutableStateOf("") }
    var proofUri by remember { mutableStateOf<Uri?>(null) }
    var menuItems by remember { mutableStateOf<Map<Int, Int>>(emptyMap()) }
    // The Exclusive Venue is only reserved once part of it is paid online.
    var payment by remember { mutableStateOf(if (vm.payMongoEnabled) "paymongo" else "gcash") }
    LaunchedEffect(vm.payMongoEnabled) { if (payment == "paymongo" && !vm.payMongoEnabled) payment = "gcash" }
    var paymentPlan by remember { mutableStateOf("downpayment") }
    val context = LocalContext.current
    val calendar = remember { Calendar.getInstance(java.util.TimeZone.getTimeZone("Asia/Manila")) }
    val dateFormat = remember { SimpleDateFormat("yyyy-MM-dd HH:mm", Locale.US).apply { timeZone = java.util.TimeZone.getTimeZone("Asia/Manila") } }
    val proofPicker = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri -> uri?.let { proofUri = it } }
    val foodCategories = remember(vm.products) { vm.products.groupBy { it.category ?: "Favorites" } }
    val selectedFoodTotal = menuItems.mapNotNull { entry -> vm.products.find { it.id == entry.key }?.price?.times(entry.value) }.sum()
    val selectedFoodCount = menuItems.values.sum()
    val bookingTotal = vm.exclusiveFee + selectedFoodTotal
    // Same rounding as the server: the downpayment share rounded up to the centavo.
    val payNow = if (paymentPlan == "full") bookingTotal else kotlin.math.ceil(kotlin.math.round(bookingTotal * 100) * vm.exclusiveDownpaymentPercent / 100.0) / 100.0
    val canPay = (payment == "paymongo" && vm.payMongoEnabled) || (payment == "gcash" && reference.length == 13 && proofUri != null)
    val missing = when {
        !phone.matches(Regex("09\\d{9}")) -> "Enter an 11-digit phone number starting with 09."
        date.isBlank() -> "Choose the date of your event."
        !canPay -> "Enter the 13-digit GCash reference and attach your payment proof."
        else -> null
    }
    // null shows every dish; the food picker is one sliding row instead of one row per category.
    var foodCategory by rememberSaveable { mutableStateOf<String?>(null) }
    LaunchedEffect(foodCategories.keys) { if (foodCategory != null && foodCategory !in foodCategories) foodCategory = null }
    val shownFoods = foodCategory?.let { foodCategories[it].orEmpty() } ?: vm.products
    val scrollState = rememberScrollState()
    val scope = rememberCoroutineScope()
    var paymentTop by remember { mutableIntStateOf(0) }
    val submit = {
        vm.placeReservation(context, "exclusive", phone, date, "", guests.toString(), notes, foodRequest, menuItems, payment, reference, proofUri, paymentPlan = paymentPlan) { ok ->
            if (ok) {
                menuItems = emptyMap()
                reference = ""
                proofUri = null
                setMessage(if (payment == "paymongo") "Reservation request submitted. Complete your payment on PayMongo." else "Reservation request submitted.")
            }
        }
    }

    Column(Modifier.fillMaxSize().imePadding()) {
    Column(Modifier.weight(1f).fillMaxWidth().verticalScroll(scrollState).padding(top = 18.dp, bottom = 20.dp)) {
        Column(Modifier.padding(horizontal = 16.dp)) {
            ScreenHeader("Book a reservation", "Reserve")
            Spacer(Modifier.height(16.dp))
            Surface(Modifier.fillMaxWidth(), shape = RoundedCornerShape(22.dp), color = KColors.Ink) {
                Column(Modifier.padding(20.dp)) {
                    Eyebrow("Exclusive Venue", color = KColors.Lime)
                    Text("Kermit's is all yours for the whole day.", color = Color.White, fontSize = 22.sp, lineHeight = 27.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(top = 6.dp))
                    Row(Modifier.fillMaxWidth().padding(top = 16.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        HeroFact("Venue fee", money(vm.exclusiveFee), Modifier.weight(1.3f))
                        HeroFact("Guests", "$EXCLUSIVE_MIN_GUESTS–$EXCLUSIVE_MAX_GUESTS", Modifier.weight(1f))
                        HeroFact("Downpayment", "${vm.exclusiveDownpaymentPercent}%", Modifier.weight(1f))
                    }
                }
            }
            Spacer(Modifier.height(14.dp))
            SectionCard("Contact and date", step = 1, subtitle = "Book at least 1 day ahead. Open 8 AM–11 PM, Philippine time.") {
                OutlinedTextField(phone, { phone = it.filter(Char::isDigit).take(11) }, label = { Text("Phone number") }, placeholder = { Text("09XXXXXXXXX") }, singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number), colors = loginFieldColors(), shape = RoundedCornerShape(12.dp), modifier = Modifier.fillMaxWidth())
                Spacer(Modifier.height(10.dp))
                DateTimePickerField(date, "Event date", "Select the date", "The venue is yours from opening to closing.") { showDateTimePicker(context, calendar, dateFormat) { date = it } }
                ReservationSlotChoices(vm, date, "exclusive", guests) { date = it }
            }
            Spacer(Modifier.height(14.dp))
            SectionCard("Guests", step = 2, subtitle = "The venue fits $EXCLUSIVE_MIN_GUESTS to $EXCLUSIVE_MAX_GUESTS guests.") {
                Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.Center) {
                    FilledTonalIconButton(onClick = { guests-- }, enabled = guests > EXCLUSIVE_MIN_GUESTS, colors = IconButtonDefaults.filledTonalIconButtonColors(containerColor = KColors.LimeSoft, contentColor = KColors.Ink), modifier = Modifier.size(48.dp).semantics { contentDescription = "Fewer guests" }) { Icon(Icons.Default.Remove, contentDescription = null) }
                    Column(Modifier.width(110.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                        Text(guests.toString(), fontSize = 34.sp, fontWeight = FontWeight.Black)
                        Text("guests", color = KColors.Muted, fontSize = 12.sp)
                    }
                    FilledTonalIconButton(onClick = { guests++ }, enabled = guests < EXCLUSIVE_MAX_GUESTS, colors = IconButtonDefaults.filledTonalIconButtonColors(containerColor = KColors.LimeSoft, contentColor = KColors.Ink), modifier = Modifier.size(48.dp).semantics { contentDescription = "More guests" }) { Icon(Icons.Default.Add, contentDescription = null) }
                }
            }
            Spacer(Modifier.height(22.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(Modifier.size(28.dp).background(KColors.Ink, androidx.compose.foundation.shape.CircleShape), contentAlignment = Alignment.Center) {
                    Text("3", color = KColors.Lime, fontSize = 13.sp, fontWeight = FontWeight.Black)
                }
                Column(Modifier.weight(1f).padding(start = 10.dp)) {
                    Text("Food", fontSize = 17.sp, fontWeight = FontWeight.Bold, modifier = Modifier.semantics { heading() })
                    Text("Optional. Swipe through the dishes or pick a category.", color = KColors.Muted, fontSize = 12.sp, lineHeight = 17.sp)
                }
                if (selectedFoodCount > 0) {
                    Column(horizontalAlignment = Alignment.End) {
                        Text(money(selectedFoodTotal), color = KColors.Olive, fontWeight = FontWeight.Black)
                        Text("$selectedFoodCount selected", color = KColors.Muted, fontSize = 11.sp)
                    }
                }
            }
        }
        if (vm.products.isEmpty()) {
            Text("No foods are available right now. Pull down to refresh.", color = KColors.Muted, fontSize = 13.sp, modifier = Modifier.padding(horizontal = 16.dp, vertical = 10.dp))
        } else {
            Row(Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()).padding(start = 16.dp, end = 8.dp, top = 12.dp)) {
                KChip(foodCategory == null, if (selectedFoodCount > 0) "All · $selectedFoodCount" else "All", onClick = { foodCategory = null })
                foodCategories.forEach { (category, products) ->
                    val chosenInCategory = products.sumOf { menuItems[it.id] ?: 0 }
                    KChip(foodCategory == category, if (chosenInCategory > 0) "$category · $chosenInCategory" else category, onClick = { foodCategory = category })
                }
            }
            val rowState = rememberLazyListState()
            LaunchedEffect(foodCategory) { rowState.scrollToItem(0) }
            LazyRow(
                state = rowState,
                flingBehavior = rememberSnapFlingBehavior(rowState),
                contentPadding = PaddingValues(horizontal = 16.dp),
                horizontalArrangement = Arrangement.spacedBy(10.dp),
                modifier = Modifier.fillMaxWidth().padding(top = 6.dp),
            ) {
                items(shownFoods, key = { "reserve-food-${it.id}" }) { product ->
                    ReservationFoodCard(product, menuItems[product.id] ?: 0, Modifier.width(138.dp)) { quantity ->
                        menuItems = if (quantity <= 0) menuItems - product.id else menuItems + (product.id to quantity.coerceAtMost(RESERVATION_FOOD_MAX))
                    }
                }
            }
        }
        Column(Modifier.padding(horizontal = 16.dp)) {
            Spacer(Modifier.height(16.dp))
            OutlinedTextField(foodRequest, { foodRequest = it.take(2000) }, label = { Text("Food instructions (optional)") }, placeholder = { Text("Allergies, spice level, serving time…") }, minLines = 2, colors = loginFieldColors(), shape = RoundedCornerShape(12.dp), modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(10.dp))
            OutlinedTextField(notes, { notes = it.take(2000) }, label = { Text("Additional notes (optional)") }, minLines = 2, colors = loginFieldColors(), shape = RoundedCornerShape(12.dp), modifier = Modifier.fillMaxWidth())
        }
        Column(Modifier.padding(horizontal = 16.dp).onGloballyPositioned { paymentTop = it.positionInParent().y.toInt() }) {
            Spacer(Modifier.height(22.dp))
            SectionCard("Payment", step = 4, subtitle = "Pay at least ${vm.exclusiveDownpaymentPercent}% online now to secure the date.") {
                Text("How much to pay now", color = KColors.Muted, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                Row(Modifier.padding(top = 6.dp).horizontalScroll(rememberScrollState())) {
                    KChip(paymentPlan == "downpayment", "${vm.exclusiveDownpaymentPercent}% downpayment", onClick = { paymentPlan = "downpayment" })
                    KChip(paymentPlan == "full", "Pay in full", onClick = { paymentPlan = "full" })
                }
                Text("Pay with", color = KColors.Muted, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.padding(top = 12.dp))
                Row(Modifier.padding(top = 6.dp).horizontalScroll(rememberScrollState())) {
                    if (vm.payMongoEnabled) KChip(payment == "paymongo", "PayMongo", onClick = { payment = "paymongo" })
                    KChip(payment == "gcash", "GCash", onClick = { payment = "gcash" })
                }
                if (payment == "paymongo") PayMongoNote()
                if (payment == "gcash") {
                    vm.gcashQrUrl?.let { AsyncImage(it, "GCash QR code", Modifier.fillMaxWidth().height(160.dp).padding(vertical = 8.dp), contentScale = ContentScale.Inside) }
                    OutlinedTextField(reference, { reference = it.filter(Char::isDigit).take(13) }, label = { Text("13-digit GCash reference") }, singleLine = true, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number), colors = loginFieldColors(), shape = RoundedCornerShape(12.dp), modifier = Modifier.fillMaxWidth())
                    Spacer(Modifier.height(8.dp))
                    PaymentProofAttachment(proofUri = proofUri, onChoose = { proofPicker.launch("image/*") }, onRemove = { proofUri = null })
                }
            }
            Spacer(Modifier.height(14.dp))
            KCard(Modifier.fillMaxWidth()) {
                Text("Summary", fontSize = 17.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(bottom = 6.dp))
                ReceiptLine("Venue fee", money(vm.exclusiveFee))
                ReceiptLine(if (selectedFoodCount > 0) "Food ($selectedFoodCount)" else "Food", money(selectedFoodTotal))
                HorizontalDivider(color = KColors.Line, modifier = Modifier.padding(vertical = 8.dp))
                ReceiptLine("Estimated total", money(bookingTotal), emphasized = true)
                ReceiptLine("Pay now", money(payNow))
                ReceiptLine("Balance on the event day", money(bookingTotal - payNow))
            }
        }
    }
    // Always on screen, so the customer can pay without scrolling past the menu. Until the form is complete
    // the button takes them to the first section that still needs something.
    Surface(color = KColors.Surface, shadowElevation = 12.dp, modifier = Modifier.fillMaxWidth()) {
        Row(Modifier.padding(horizontal = 16.dp, vertical = 10.dp), verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f).padding(end = 12.dp)) {
                Text(if (paymentPlan == "full") "Pay in full now" else "${vm.exclusiveDownpaymentPercent}% downpayment now", color = KColors.Muted, fontSize = 11.sp, maxLines = 1)
                Text(money(payNow), color = KColors.Ink, fontSize = 19.sp, fontWeight = FontWeight.Black, maxLines = 1)
                Text(
                    missing ?: "Total ${money(bookingTotal)}${if (selectedFoodCount > 0) " · $selectedFoodCount food" else ""}",
                    color = if (missing != null) KColors.Warning else KColors.Muted,
                    fontSize = 11.sp,
                    lineHeight = 14.sp,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                )
            }
            PrimaryButton(
                when {
                    vm.busy -> "Submitting..."
                    missing != null -> "Continue"
                    payment == "paymongo" -> "Request & pay"
                    else -> "Request reservation"
                },
                onClick = {
                    if (missing == null) submit()
                    else scope.launch { scrollState.animateScrollTo(if (phone.matches(Regex("09\\d{9}")) && date.isNotBlank()) paymentTop else 0) }
                },
                enabled = !vm.busy && guests in EXCLUSIVE_MIN_GUESTS..EXCLUSIVE_MAX_GUESTS,
                modifier = Modifier.weight(1f),
            )
        }
    }
    }
}

@Composable
private fun HeroFact(label: String, value: String, modifier: Modifier = Modifier) {
    Column(modifier.background(KColors.InkSoft, RoundedCornerShape(14.dp)).padding(horizontal = 12.dp, vertical = 10.dp)) {
        Text(label, color = Color(0xFFA9ADA4), fontSize = 11.sp, maxLines = 1)
        Text(value, color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.Bold, maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.padding(top = 2.dp))
    }
}

@Composable
private fun ReservationFoodCard(product: Product, quantity: Int, modifier: Modifier = Modifier, onQuantity: (Int) -> Unit) {
    val selected = quantity > 0
    KCard(
        modifier,
        contentPadding = 0.dp,
        border = androidx.compose.foundation.BorderStroke(if (selected) 2.dp else 1.dp, if (selected) KColors.Lime else KColors.Line),
    ) {
        Box {
            ProductImage(product, Modifier.fillMaxWidth())
            if (selected) {
                Surface(shape = RoundedCornerShape(50), color = KColors.Ink, modifier = Modifier.align(Alignment.TopEnd).padding(8.dp)) {
                    Text("× $quantity", color = KColors.Lime, fontSize = 12.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 9.dp, vertical = 3.dp))
                }
            }
        }
        Column(Modifier.padding(horizontal = 10.dp, vertical = 8.dp)) {
            Text(product.name, fontSize = 13.sp, lineHeight = 17.sp, fontWeight = FontWeight.ExtraBold, maxLines = 2, minLines = 2, overflow = TextOverflow.Ellipsis)
            Text(money(product.price), color = KColors.Olive, fontSize = 13.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(top = 2.dp, bottom = 6.dp))
            QuantityStepper(product.name, quantity, canIncrease = quantity < RESERVATION_FOOD_MAX, onChange = onQuantity, modifier = Modifier.fillMaxWidth())
        }
    }
}

@Composable
private fun ReservationDetailDialog(reservation: Reservation, onPayMongo: (() -> Unit)?, close: () -> Unit) {
    val exclusive = reservation.type == "exclusive"
    Dialog(onDismissRequest = close, properties = DialogProperties(usePlatformDefaultWidth = false)) {
        Surface(
            modifier = Modifier.fillMaxWidth().fillMaxHeight(0.88f).padding(horizontal = 16.dp).widthIn(max = 560.dp),
            shape = RoundedCornerShape(22.dp),
            color = KColors.Canvas,
            shadowElevation = 12.dp,
        ) {
            Column(Modifier.fillMaxSize()) {
                LazyColumn(Modifier.fillMaxWidth().weight(1f), contentPadding = PaddingValues(20.dp)) {
                    item(key = "reservation-head") {
                        Eyebrow(if (exclusive) "Exclusive Venue" else "Table reservation")
                        Row(Modifier.fillMaxWidth().padding(top = 4.dp), verticalAlignment = Alignment.CenterVertically) {
                            Text(reservation.reference, fontSize = 24.sp, fontWeight = FontWeight.Black, modifier = Modifier.weight(1f).semantics { heading() })
                            StatusPill(reservation.status)
                        }
                        KCard(Modifier.fillMaxWidth().padding(top = 14.dp)) {
                            ReceiptLine("Schedule", reservationScheduleLabel(reservation))
                            ReceiptLine("Party", "${reservation.guests ?: reservation.table_size ?: 0} guests")
                            reservation.table_label?.takeIf { !exclusive }?.let { ReceiptLine("Table", it) }
                            reservation.hold_expires_at?.let { ReceiptLine("Approval deadline", receiptDate(it)) }
                            ReceiptLine("Payment", paymentMethodLabel(reservation.payment_method))
                            ReceiptLine("Payment status", reservation.payment_status.replaceFirstChar { it.uppercase() })
                            reservation.payment_reference?.let { ReceiptLine("GCash reference", it) }
                        }
                        if (reservation.items.isNotEmpty()) Eyebrow("Food", modifier = Modifier.padding(top = 18.dp, bottom = 4.dp))
                    }
                    items(reservation.items, key = { "reservation-item-${it.product_id}" }) { item ->
                        Row(Modifier.fillMaxWidth().padding(vertical = 9.dp), verticalAlignment = Alignment.Top) {
                            Column(Modifier.weight(1f).padding(end = 12.dp)) {
                                Text(item.name, fontWeight = FontWeight.Bold)
                                Text("${item.quantity} × ${money(item.unit_price)}", color = KColors.Muted, fontSize = 12.sp)
                            }
                            Text(money(item.subtotal), fontWeight = FontWeight.Bold)
                        }
                        HorizontalDivider(color = KColors.Line)
                    }
                    item(key = "reservation-totals") {
                        KCard(Modifier.fillMaxWidth().padding(top = 14.dp)) {
                            ReceiptLine(if (exclusive) "Venue fee" else "Reservation fee", money(reservation.reservation_fee))
                            ReceiptLine("Food total", money(reservation.food_total))
                            HorizontalDivider(color = KColors.Line, modifier = Modifier.padding(vertical = 8.dp))
                            ReceiptLine("Total", money(reservation.total_amount), emphasized = true)
                            reservation.downpayment_amount?.let { ReceiptLine("Due online", money(it)) }
                            if (reservation.amount_paid > 0) ReceiptLine("Paid", money(reservation.amount_paid))
                            if (reservation.balance_due > 0) ReceiptLine("Balance on the event day", money(reservation.balance_due))
                        }
                    }
                }
                HorizontalDivider(color = KColors.Line)
                Column(Modifier.padding(16.dp)) {
                    onPayMongo?.let {
                        PrimaryButton("Pay with PayMongo", onClick = it, container = KColors.Olive)
                        Spacer(Modifier.height(10.dp))
                    }
                    SecondaryButton("Close", onClick = close)
                }
            }
        }
    }
}
private val receiptDateFormatter = DateTimeFormatter.ofPattern("MMM d, yyyy · h:mm a", Locale.US)
private val reservationHistoryDateFormatter = DateTimeFormatter.ofPattern("EEEE, MMMM d, yyyy", Locale.US)

private fun reservationHistoryDay(value: String): String = runCatching {
    OffsetDateTime.parse(value).format(reservationHistoryDateFormatter)
}.getOrDefault(value.substringBefore('T').substringBefore(' '))

private fun reservationHistorySortKey(value: String): String = runCatching {
    OffsetDateTime.parse(value).toLocalDate().toString()
}.getOrDefault(value)

private fun receiptDate(value: String?): String {
    if (value.isNullOrBlank()) return "Not available"

    return runCatching { OffsetDateTime.parse(value).format(receiptDateFormatter) }.getOrDefault(value)
}

private fun money(value: Double) = "₱${String.format(Locale.US, "%,.2f", value)}"

private fun Uri.toMultipart(context: Context, fieldName: String): MultipartBody.Part? {
    val type = context.contentResolver.getType(this) ?: "image/jpeg"
    val extension = type.substringAfter('/', "jpg")
    val bytes = context.contentResolver.openInputStream(this)?.use { it.readBytes() } ?: return null
    val body = bytes.toRequestBody(type.toMediaTypeOrNull())

    return MultipartBody.Part.createFormData(fieldName, "$fieldName.$extension", body)
}

@Composable
private fun CartAmount(amount: Double) {
    AnimatedContent(
        targetState = money(amount),
        modifier = Modifier.animateContentSize(tween(160)),
        transitionSpec = { fadeIn(tween(160)) togetherWith fadeOut(tween(90)) },
        label = "cartAmount",
    ) { value -> Text(value, fontSize = 19.sp, fontWeight = FontWeight.Black) }
}
// Customers book an arrival time only; staff free the table when they leave.
// The Exclusive Venue takes the whole day.
private fun reservationScheduleLabel(reservation: Reservation): String =
    if (reservation.type == "exclusive") "${reservationHistoryDay(reservation.reservation_at)} · whole day" else receiptDate(reservation.reservation_at)

@Composable
private fun ReservationSlotChoices(vm: AppViewModel, date: String, type: String, guests: Int, tableId: Int? = null, onSelect: (String) -> Unit) {
    var slots by remember { mutableStateOf<List<ReservationSlot>>(emptyList()) }
    var message by remember { mutableStateOf("") }
    LaunchedEffect(date.take(10), type, guests, tableId, vm.refreshCount) {
        slots = emptyList()
        if (date.isNotBlank()) {
            message = "Checking availability..."
            try {
                slots = vm.reservationSlots(date.take(10), type, guests, tableId)
                // The Exclusive Venue is one whole-day slot, so there is nothing to choose.
                if (type == "exclusive") slots.firstOrNull { it.available }?.let { onSelect(it.start.replace('T', ' ')) }
                message = when {
                    type == "exclusive" && slots.any { it.available } -> "Kermit's is free for the whole day on this date."
                    type == "exclusive" -> "Kermit's is already booked on this date, or it is too soon to reserve it. Choose another date."
                    slots.any { it.available } -> "Available times; checked again at submission."
                    tableId != null -> "That table is fully booked on this date. Choose another table, any table, or another date."
                    else -> "No availability. Please choose another date."
                }
            } catch (error: Exception) {
                if (error is kotlinx.coroutines.CancellationException) throw error
                message = "Could not check availability. Please choose the date again."
            }
        }
    }
    // Keep "tables left" current while the customer decides.
    LaunchedEffect(date.take(10), type, guests, tableId) {
        while (date.isNotBlank()) {
            delay(60_000)
            runCatching { vm.reservationSlots(date.take(10), type, guests, tableId) }.onSuccess { slots = it }
        }
    }
    if (message.isNotEmpty()) Text(message, fontSize = 12.sp)
    Row(Modifier.horizontalScroll(rememberScrollState())) {
        slots.forEach { slot ->
            val left = slot.tables_left
            val label = if (tableId == null && slot.available && left != null) "${slot.label} · $left left" else slot.label
            KChip(selected = date.replace(' ', 'T') == slot.start, label = label, enabled = slot.available, onClick = { onSelect(slot.start.replace('T', ' ')) })
        }
    }
}

@Composable
private fun TableChoice(vm: AppViewModel, guests: Int, selected: Int?, onSelect: (Int?) -> Unit) {
    val fitting = vm.diningTables.filter { it.seats >= guests }.sortedBy { it.number }
    LaunchedEffect(guests, vm.diningTables) { if (selected != null && fitting.none { it.id == selected }) onSelect(null) }
    if (vm.diningTables.isEmpty()) return
    Text("Table (optional)", color = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.padding(top = 8.dp))
    Row(Modifier.horizontalScroll(rememberScrollState())) {
        KChip(selected = selected == null, label = "Any table", onClick = { onSelect(null) })
        fitting.forEach { table ->
            KChip(selected = selected == table.id, label = "Table ${table.number} · ${table.seats} seats", onClick = { onSelect(table.id) })
        }
    }
}
