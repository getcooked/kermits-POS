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
    val keepsSession: Boolean get() = prefs.getBoolean("keep_signed_in", false)

    fun saveSession(token: String, keepSignedIn: Boolean) {
        prefs.edit()
            .putString("token", token)
            .putBoolean("keep_signed_in", keepSignedIn)
            .apply()
    }

    fun clear() = prefs.edit().clear().apply()
}
