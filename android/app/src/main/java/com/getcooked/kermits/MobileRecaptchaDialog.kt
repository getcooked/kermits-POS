package com.getcooked.kermits

import android.annotation.SuppressLint
import android.graphics.Bitmap
import android.net.Uri
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.widthIn
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties

@SuppressLint("SetJavaScriptEnabled")
@Composable
internal fun MobileRecaptchaDialog(url: String, onVerified: (String) -> Unit, onDismiss: () -> Unit) {
    val allowedHost = remember(url) { Uri.parse(url).host }
    var webView by remember { mutableStateOf<WebView?>(null) }
    var loading by remember { mutableStateOf(true) }
    var error by remember { mutableStateOf<String?>(null) }
    var delivered by remember { mutableStateOf(false) }

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
            shape = MaterialTheme.shapes.large,
            color = Color(0xFFF7F7F1),
            modifier = Modifier.fillMaxWidth().padding(18.dp).widthIn(max = 430.dp),
        ) {
            Column {
                Box(Modifier.fillMaxWidth().padding(horizontal = 12.dp, vertical = 8.dp)) {
                    Text("Verify you are human", style = MaterialTheme.typography.titleMedium, modifier = Modifier.align(Alignment.CenterStart))
                    IconButton(onClick = onDismiss, modifier = Modifier.align(Alignment.CenterEnd)) {
                        Text("Close", style = MaterialTheme.typography.labelLarge)
                    }
                }
                Box(Modifier.fillMaxWidth().height(430.dp)) {
                    AndroidView(
                        factory = { context ->
                            WebView(context).apply {
                                webView = this
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
                                    }

                                    override fun onPageFinished(view: WebView?, pageUrl: String?) {
                                        loading = false
                                    }

                                    override fun shouldOverrideUrlLoading(view: WebView?, request: WebResourceRequest): Boolean {
                                        val destination = request.url
                                        if (destination.scheme == "kermits-recaptcha" && destination.host == "success") {
                                            val token = destination.getQueryParameter("token")
                                            if (!delivered && !token.isNullOrBlank()) {
                                                delivered = true
                                                onVerified(token)
                                            }
                                            return true
                                        }
                                        if (!request.isForMainFrame) return false

                                        return destination.scheme != "https" || destination.host != allowedHost
                                    }

                                    override fun onReceivedError(view: WebView?, request: WebResourceRequest, webError: WebResourceError?) {
                                        if (request.isForMainFrame) {
                                            loading = false
                                            error = "Unable to load reCAPTCHA. Check your connection and try again."
                                        }
                                    }
                                }
                                loadUrl(url)
                            }
                        },
                        modifier = Modifier.fillMaxSize(),
                    )
                    if (loading) CircularProgressIndicator(Modifier.align(Alignment.Center))
                    error?.let { message ->
                        Text(
                            message,
                            color = MaterialTheme.colorScheme.error,
                            modifier = Modifier.align(Alignment.Center).padding(24.dp),
                        )
                    }
                }
            }
        }
    }
}
