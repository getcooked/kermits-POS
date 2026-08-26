package com.getcooked.kermits

import com.squareup.moshi.Json
import com.squareup.moshi.JsonClass
import okhttp3.MultipartBody
import okhttp3.RequestBody
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.RequestBody.Companion.toRequestBody
import retrofit2.Response
import retrofit2.http.*

@JsonClass(generateAdapter = true)
data class LoginRequest(val login: String, val password: String, val device_name: String = "Android device")
@JsonClass(generateAdapter = true)
data class User(val id: Int, val name: String, val username: String, val email: String, val phone: String?, val role: String)
@JsonClass(generateAdapter = true)
data class Product(val id: Int, val name: String, val category: String?, val description: String?, val price: Double, val stock: Int, val image_url: String?)
@JsonClass(generateAdapter = true)
data class OrderItem(val product_id: Int, val name: String, val quantity: Int, val unit_price: Double, val subtotal: Double)
@JsonClass(generateAdapter = true)
data class Order(val id: Int, val total: Double, val payment_method: String, val payment_status: String, val created_at: String?, val items: List<OrderItem> = emptyList())
@JsonClass(generateAdapter = true)
data class Reservation(val id: Int, val reference: String, val type: String, val table_size: Int?, val guests: Int?, val reservation_at: String, val total_amount: Double, val payment_method: String, val status: String, val notes: String?, val items: List<OrderItem> = emptyList())
@JsonClass(generateAdapter = true)
data class LoginData(val token: String, val user: User)
@JsonClass(generateAdapter = true)
data class LoginResponse(val data: LoginData)
@JsonClass(generateAdapter = true)
data class ApiError(val message: String? = null, val errors: Map<String, List<String>>? = null)
@JsonClass(generateAdapter = true)
data class CatalogData(val products: List<Product>, val gcash_qr_url: String?)
@JsonClass(generateAdapter = true)
data class CatalogResponse(val data: CatalogData)
@JsonClass(generateAdapter = true)
data class ListOrdersResponse(val data: List<Order>)
@JsonClass(generateAdapter = true)
data class ListReservationsResponse(val data: List<Reservation>)

fun String.formPart(): RequestBody = toRequestBody("text/plain".toMediaType())

interface KermitsApi {
    @POST("login") suspend fun login(@Body request: LoginRequest): Response<LoginResponse>
    @GET("me") suspend fun me(): Map<String, User>
    @POST("logout") suspend fun logout(): Response<Unit>
    @GET("products") suspend fun products(): CatalogResponse
    @GET("orders") suspend fun orders(): ListOrdersResponse
    @POST("orders") suspend fun createOrder(@Body body: Map<String, Any>): retrofit2.Response<Map<String, Order>>
    @GET("reservations") suspend fun reservations(): ListReservationsResponse
    @Multipart
    @POST("reservations") suspend fun createReservation(
        @Part("type") type: RequestBody, @Part("table_size") tableSize: RequestBody?,
        @Part("phone") phone: RequestBody, @Part("reservation_at") at: RequestBody,
        @Part("guests") guests: RequestBody?, @Part("notes") notes: RequestBody?,
        @Part("payment_method") payment: RequestBody, @Part("payment_reference") reference: RequestBody?,
        @Part proof: MultipartBody.Part? = null
    ): retrofit2.Response<Map<String, Reservation>>
}
