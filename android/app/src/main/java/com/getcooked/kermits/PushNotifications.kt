package com.getcooked.kermits

import android.Manifest
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import com.google.firebase.messaging.FirebaseMessaging
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch

object PushNotifications {
    const val CHANNEL_ID = "reservation_updates"
    private const val MESSAGE_TYPE = "reservation.updated"
    private val networkScope = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    fun createChannel(context: Context) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return

        val channel = NotificationChannel(
            CHANNEL_ID,
            context.getString(R.string.reservation_updates_channel_name),
            NotificationManager.IMPORTANCE_HIGH,
        ).apply {
            description = context.getString(R.string.reservation_updates_channel_description)
        }
        context.getSystemService(NotificationManager::class.java).createNotificationChannel(channel)
    }

    fun clearShownNotifications(context: Context) {
        NotificationManagerCompat.from(context).cancelAll()
    }

    fun shouldRequestPermission(context: Context, store: SessionStore): Boolean =
        BuildConfig.FCM_CONFIGURED &&
            Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
            !store.notificationPermissionRequested &&
            ContextCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED

    fun sync(context: Context) {
        if (!BuildConfig.FCM_CONFIGURED) return

        val appContext = context.applicationContext
        val store = SessionStore(appContext)
        store.firebaseInstallationId?.let { upload(appContext, it) }
        runCatching { FirebaseMessaging.getInstance().register() }
    }

    fun registered(context: Context, installationId: String) {
        if (!BuildConfig.FCM_CONFIGURED || installationId.isBlank()) return

        val appContext = context.applicationContext
        SessionStore(appContext).firebaseInstallationId = installationId
        upload(appContext, installationId)
    }

    fun unregistered(context: Context, installationId: String) {
        val appContext = context.applicationContext
        val store = SessionStore(appContext)
        if (store.firebaseInstallationId != installationId) return
        store.firebaseInstallationId = null

        if (store.token.isNullOrBlank()) return
        networkScope.launch {
            runCatching { ApiClient.create(store).deletePushInstallation() }
        }
    }

    private fun upload(context: Context, installationId: String) {
        val store = SessionStore(context)
        if (store.token.isNullOrBlank()) return

        networkScope.launch {
            runCatching {
                ApiClient.create(store).registerPushInstallation(
                    PushInstallationRequest(identifier = installationId),
                )
            }
        }
    }

    fun showReservationUpdate(context: Context, data: Map<String, String>) {
        if (data["type"] != MESSAGE_TYPE) return

        val store = SessionStore(context)
        val signedInUserId = store.userId ?: return
        val targetUserId = data["user_id"]?.toIntOrNull() ?: return
        if (store.token.isNullOrBlank() || targetUserId != signedInUserId) return
        if (
            Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
            ContextCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED
        ) return

        createChannel(context)
        val reservationId = data["reservation_id"]?.toIntOrNull()
        val notificationKey = data["event_id"] ?: "reservation-${reservationId ?: 0}"
        val intent = Intent(context, MainActivity::class.java).apply {
            action = "com.getcooked.kermits.OPEN_RESERVATION_UPDATE"
            flags = Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
            reservationId?.let { putExtra("reservation_id", it) }
        }
        val pendingIntent = PendingIntent.getActivity(
            context,
            notificationKey.hashCode(),
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )
        val title = data["title"].orEmpty().take(100).ifBlank { "Reservation updated" }
        val body = data["body"].orEmpty().take(240).ifBlank {
            data["reference"]?.let { "Reservation $it has been updated." }
                ?: "Your reservation has been updated."
        }
        val notification = NotificationCompat.Builder(context, CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_notification)
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(NotificationCompat.BigTextStyle().bigText(body))
            .setContentIntent(pendingIntent)
            .setAutoCancel(true)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setCategory(NotificationCompat.CATEGORY_STATUS)
            .setVisibility(NotificationCompat.VISIBILITY_PRIVATE)
            .build()

        NotificationManagerCompat.from(context).notify(notificationKey.hashCode(), notification)
    }
}

class KermitsMessagingService : FirebaseMessagingService() {
    override fun onRegistered(installationId: String) {
        super.onRegistered(installationId)
        PushNotifications.registered(applicationContext, installationId)
    }

    override fun onUnregistered(installationId: String) {
        super.onUnregistered(installationId)
        PushNotifications.unregistered(applicationContext, installationId)
    }

    override fun onMessageReceived(message: RemoteMessage) {
        super.onMessageReceived(message)
        PushNotifications.showReservationUpdate(applicationContext, message.data)
    }
}
