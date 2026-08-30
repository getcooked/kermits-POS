package com.getcooked.kermits

import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.moshi.MoshiConverterFactory
import java.util.concurrent.TimeUnit

object ApiClient {
    fun create(store: SessionStore): KermitsApi {
        val clientBuilder = OkHttpClient.Builder()
            .connectTimeout(15, TimeUnit.SECONDS)
            .readTimeout(25, TimeUnit.SECONDS)
            .callTimeout(35, TimeUnit.SECONDS)
            .addInterceptor { chain ->
                chain.proceed(
                    chain.request().newBuilder().apply {
                        store.token?.let { header("Authorization", "Bearer $it") }
                        header("Accept", "application/json")
                    }.build(),
                )
            }

        if (BuildConfig.DEBUG) {
            clientBuilder.addInterceptor(
                HttpLoggingInterceptor().apply { level = HttpLoggingInterceptor.Level.BASIC },
            )
        }

        return Retrofit.Builder()
            .baseUrl(BuildConfig.API_BASE_URL)
            .client(clientBuilder.build())
            .addConverterFactory(MoshiConverterFactory.create())
            .build()
            .create(KermitsApi::class.java)
    }
}
