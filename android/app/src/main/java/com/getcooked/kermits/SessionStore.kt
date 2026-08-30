package com.getcooked.kermits

import android.content.Context
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey

class SessionStore(context: Context) {
    private val prefs = EncryptedSharedPreferences.create(
        context, "kermits_session", MasterKey.Builder(context).setKeyScheme(MasterKey.KeyScheme.AES256_GCM).build(),
        EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
        EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
    )
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
