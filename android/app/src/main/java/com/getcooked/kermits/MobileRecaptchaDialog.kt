package com.getcooked.kermits

import android.annotation.SuppressLint
import android.graphics.Bitmap
import android.net.Uri
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.VerifiedUser
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties

/** Height of the hosted page while it only shows the reCAPTCHA checkbox. */
private val CHECKBOX_HEIGHT = 96.dp

@SuppressLint("SetJavaScriptEnabled")
@Composable
internal fun MobileRecaptchaDialog(url: String, onVerified: (String) -> Unit, onDismiss: () -> Unit) {
    val allowedHost = remember(url) { Uri.parse(url).host }
    var webView by remember { mutableStateOf<WebView?>(null) }
    var loading by remember { mutableStateOf(true) }
    var error by remember { mutableStateOf<String?>(null) }
    var delivered by remember { mutableStateOf(false) }
    // The page reports when Google's picture challenge opens so the popup only grows when it is needed.
    var challengeOpen by remember { mutableStateOf(false) }
    val challengeHeight = (LocalConfiguration.current.screenHeightDp - 190).coerceIn(360, 600).dp
    val webHeight by animateDpAsState(
        targetValue = if (challengeOpen) challengeHeight else CHECKBOX_HEIGHT,
        // Grow at once so Google positions the challenge in the full space; shrink smoothly.
        animationSpec = tween(if (challengeOpen) 0 else 180),
        label = "recaptchaHeight",
    )

    DisposableEffect(Unit) {
        onDispose {
            webView?.stopLoading()
            webView?.destroy()
            webView = null
        }
    }

    Dialog(
        onDismissRequest = onDismiss,
        properties = DialogProperties(usePlatformDefaultWidth = false),
    ) {
        Surface(
            shape = RoundedCornerShape(24.dp),
            color = KColors.Surface,
            shadowElevation = 12.dp,
            modifier = Modifier.padding(horizontal = 16.dp).widthIn(max = 420.dp).fillMaxWidth(),
        ) {
            Column(Modifier.padding(top = 18.dp, bottom = 14.dp)) {
                Row(Modifier.fillMaxWidth().padding(start = 20.dp, end = 8.dp), verticalAlignment = Alignment.Top) {
                    Box(
                        Modifier.size(40.dp).clip(CircleShape).background(KColors.LimeSoft),
                        contentAlignment = Alignment.Center,
                    ) {
                        Icon(Icons.Default.VerifiedUser, contentDescription = null, tint = KColors.Olive, modifier = Modifier.size(22.dp))
                    }
                    Spacer(Modifier.width(12.dp))
                    Column(Modifier.weight(1f).padding(top = 1.dp)) {
                        Text("Quick security check", color = KColors.Ink, fontSize = 17.sp, fontWeight = FontWeight.Bold)
                        Text(
                            "Tick the box below to continue.",
                            color = KColors.Muted,
                            fontSize = 13.sp,
                            lineHeight = 18.sp,
                            modifier = Modifier.padding(top = 2.dp),
                        )
                    }
                    IconButton(onClick = onDismiss, modifier = Modifier.size(40.dp)) {
                        Icon(Icons.Default.Close, contentDescription = "Cancel security check", tint = KColors.Muted)
                    }
                }
                Spacer(Modifier.height(12.dp))
                Box(Modifier.fillMaxWidth().height(webHeight)) {
                    AndroidView(
                        factory = { context ->
                            WebView(context).apply {
                                webView = this
                                setBackgroundColor(android.graphics.Color.WHITE)
                                isVerticalScrollBarEnabled = false
                                overScrollMode = WebView.OVER_SCROLL_NEVER
                                settings.javaScriptEnabled = true
                                settings.domStorageEnabled = true
                                settings.allowFileAccess = false
                                settings.allowContentAccess = false
                                settings.javaScriptCanOpenWindowsAutomatically = false
                                settings.setSupportMultipleWindows(false)
                                settings.mixedContentMode = WebSettings.MIXED_CONTENT_NEVER_ALLOW
                                settings.safeBrowsingEnabled = true
                                webViewClient = object : WebViewClient() {
                                    override fun onPageStarted(view: WebView?, pageUrl: String?, favicon: Bitmap?) {
                                        loading = true
                                        error = null
                                        challengeOpen = false
                                    }

                                    override fun onPageFinished(view: WebView?, pageUrl: String?) {
                                        loading = false
                                    }

                                    override fun shouldOverrideUrlLoading(view: WebView?, request: WebResourceRequest): Boolean {
                                        val destination = request.url
                                        if (destination.scheme == "kermits-recaptcha") {
                                            when (destination.host) {
                                                "success" -> {
                                                    val token = destination.getQueryParameter("token")
                                                    if (!delivered && !token.isNullOrBlank()) {
                                                        delivered = true
                                                        onVerified(token)
                                                    }
                                                }
                                                "layout" -> challengeOpen = destination.getQueryParameter("challenge") == "1"
                                            }
                                            return true
                                        }
                                        if (!request.isForMainFrame) return false

                                        return destination.scheme != "https" || destination.host != allowedHost
                                    }

                                    override fun onReceivedError(view: WebView?, request: WebResourceRequest, webError: WebResourceError?) {
                                        if (request.isForMainFrame) {
                                            loading = false
                                            challengeOpen = false
                                            error = "Unable to load the security check. Check your connection and try again."
                                        }
                                    }
                                }
                                loadUrl(url)
                            }
                        },
                        modifier = Modifier.fillMaxSize(),
                    )
                    if (loading || error != null) {
                        Column(
                            Modifier.fillMaxSize().background(KColors.Surface).padding(horizontal = 20.dp),
                            verticalArrangement = Arrangement.Center,
                            horizontalAlignment = Alignment.CenterHorizontally,
                        ) {
                            val message = error
                            if (message == null) {
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    CircularProgressIndicator(Modifier.size(18.dp), color = KColors.Olive, strokeWidth = 2.dp)
                                    Spacer(Modifier.width(10.dp))
                                    Text("Loading security check…", color = KColors.Muted, fontSize = 13.sp)
                                }
                            } else {
                                Text(message, color = KColors.Danger, fontSize = 13.sp, lineHeight = 18.sp, textAlign = TextAlign.Center)
                                TextButton(onClick = { error = null; webView?.reload() }) {
                                    Text("Try again", color = KColors.Olive, fontWeight = FontWeight.Bold)
                                }
                            }
                        }
                    }
                }
                Text(
                    "Kermit's uses Google reCAPTCHA to keep accounts safe.",
                    color = KColors.Faint,
                    fontSize = 11.sp,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.fillMaxWidth().padding(horizontal = 20.dp, vertical = 4.dp),
                )
            }
        }
    }
}
