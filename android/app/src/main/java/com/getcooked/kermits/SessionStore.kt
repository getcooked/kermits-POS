package com.getcooked.kermits

import android.content.Context
import android.content.SharedPreferences
import android.os.Build
import android.util.Log
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey

class SessionStore(context: Context) {
    private val prefs = createPreferences(context.applicationContext)

    private companion object {
        const val TAG = "SessionStore"
        const val ENCRYPTED_PREFS = "kermits_session"
        const val DEVICE_ENCRYPTED_PREFS = "kermits_session_device"
        const val DEVICE_FALLBACK_PREFS = "kermits_session_private"

        fun createPreferences(context: Context): SharedPreferences {
            try {
                return createEncryptedPreferences(context, ENCRYPTED_PREFS)
            } catch (error: Exception) {
                Log.w(TAG, "Credential-protected session storage is unavailable", error)
            }

            val deviceContext = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
                context.createDeviceProtectedStorageContext()
            } else {
                context
            }

            try {
                return createEncryptedPreferences(deviceContext, DEVICE_ENCRYPTED_PREFS)
            } catch (error: Exception) {
                Log.e(TAG, "Encrypted session storage is unavailable; using private app storage", error)
            }

            return deviceContext.getSharedPreferences(DEVICE_FALLBACK_PREFS, Context.MODE_PRIVATE)
        }

        fun createEncryptedPreferences(context: Context, name: String): SharedPreferences {
            val masterKey = MasterKey.Builder(context)
                .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
                .build()

            return EncryptedSharedPreferences.create(
                context,
                name,
                masterKey,
                EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
                EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
            )
        }
    }

    var token: String? get() = prefs.getString("token", null); set(value) = prefs.edit().putString("token", value).apply()
    var userId: Int?
        get() = if (prefs.contains("user_id")) prefs.getInt("user_id", 0) else null
        set(value) {
            prefs.edit().apply {
                if (value == null) remove("user_id") else putInt("user_id", value)
            }.apply()
        }
    var firebaseInstallationId: String?
        get() = prefs.getString("firebase_installation_id", null)
        set(value) {
            prefs.edit().apply {
                if (value.isNullOrBlank()) remove("firebase_installation_id")
                else putString("firebase_installation_id", value)
            }.apply()
        }
    var notificationPermissionRequested: Boolean
        get() = prefs.getBoolean("notification_permission_requested", false)
        set(value) = prefs.edit().putBoolean("notification_permission_requested", value).apply()
    val keepsSession: Boolean get() = prefs.getBoolean("keep_signed_in", false)

    fun saveSession(token: String, keepSignedIn: Boolean, userId: Int) {
        prefs.edit()
            .putString("token", token)
            .putInt("user_id", userId)
            .putBoolean("keep_signed_in", keepSignedIn)
            .apply()
    }

    /** Clears customer authentication without discarding this app installation's FCM identity. */
    fun clear() = prefs.edit()
        .remove("token")
        .remove("user_id")
        .remove("keep_signed_in")
        .apply()
}
