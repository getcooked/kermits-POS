package com.getcooked.kermits

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.ErrorOutline
import androidx.compose.material.icons.filled.Remove
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.FilterChip
import androidx.compose.material3.FilterChipDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.Typography
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import coil.request.ImageRequest

/** Kermit's brand palette: charcoal and olive with a lime accent on a warm canvas. */
internal object KColors {
    val Ink = Color(0xFF171817)
    val InkSoft = Color(0xFF2B2D28)
    val Olive = Color(0xFF6B7400)
    val Lime = Color(0xFFB5C019)
    val LimeSoft = Color(0xFFF0F3D8)
    val Canvas = Color(0xFFF5F5EF)
    val Surface = Color.White
    val Line = Color(0xFFE2E4DA)
    val Muted = Color(0xFF6B7066)
    val Faint = Color(0xFF9A9F94)
    val Success = Color(0xFF257342)
    val SuccessSoft = Color(0xFFE5F4E9)
    val Danger = Color(0xFFB72C2C)
    val DangerSoft = Color(0xFFFDEAEA)
    val Warning = Color(0xFF8A5A00)
    val WarningSoft = Color(0xFFFFF2CC)
    val Info = Color(0xFF315EC9)
    val InfoSoft = Color(0xFFE9EEFB)
}

@Composable
fun KermitsTheme(content: @Composable () -> Unit) {
    val base = Typography()
    MaterialTheme(
        colorScheme = lightColorScheme(
            primary = KColors.Olive,
            onPrimary = Color.White,
            primaryContainer = KColors.LimeSoft,
            onPrimaryContainer = KColors.Ink,
            secondary = KColors.Ink,
            onSecondary = Color.White,
            secondaryContainer = KColors.LimeSoft,
            onSecondaryContainer = KColors.Ink,
            tertiary = KColors.Olive,
            surfaceContainerHigh = Color.White,
            background = KColors.Canvas,
            surface = KColors.Surface,
            onSurface = KColors.Ink,
            surfaceVariant = Color(0xFFF4F5EE),
            onSurfaceVariant = KColors.Muted,
            outline = KColors.Line,
            error = KColors.Danger,
        ),
        typography = base.copy(
            headlineLarge = base.headlineLarge.copy(fontWeight = FontWeight.Bold),
            headlineMedium = base.headlineMedium.copy(fontWeight = FontWeight.Bold),
        ),
        content = content,
    )
}

@Composable
internal fun Eyebrow(text: String, modifier: Modifier = Modifier, color: Color = KColors.Olive) {
    Text(text.uppercase(), color = color, fontSize = 11.sp, letterSpacing = 1.4.sp, fontWeight = FontWeight.ExtraBold, modifier = modifier)
}

@Composable
internal fun ScreenHeader(
    eyebrow: String,
    title: String,
    modifier: Modifier = Modifier,
    subtitle: String? = null,
    actions: @Composable RowScope.() -> Unit = {},
) {
    Row(modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        Column(Modifier.weight(1f)) {
            Eyebrow(eyebrow)
            Text(title, color = KColors.Ink, fontSize = 28.sp, lineHeight = 32.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(top = 4.dp).semantics { heading() })
            subtitle?.let { Text(it, color = KColors.Muted, fontSize = 13.sp, lineHeight = 18.sp, modifier = Modifier.padding(top = 4.dp)) }
        }
        actions()
    }
}

@Composable
internal fun KCard(
    modifier: Modifier = Modifier,
    onClick: (() -> Unit)? = null,
    color: Color = KColors.Surface,
    border: BorderStroke? = BorderStroke(1.dp, KColors.Line),
    contentPadding: Dp = 16.dp,
    content: @Composable ColumnScope.() -> Unit,
) {
    val shape = RoundedCornerShape(18.dp)
    if (onClick != null) {
        Surface(onClick = onClick, modifier = modifier, shape = shape, color = color, border = border) { Column(Modifier.padding(contentPadding), content = content) }
    } else {
        Surface(modifier = modifier, shape = shape, color = color, border = border) { Column(Modifier.padding(contentPadding), content = content) }
    }
}

/** A titled card for one part of a longer form, optionally numbered as a step. */
@Composable
internal fun SectionCard(
    title: String,
    modifier: Modifier = Modifier,
    step: Int? = null,
    subtitle: String? = null,
    trailing: @Composable () -> Unit = {},
    content: @Composable ColumnScope.() -> Unit,
) {
    KCard(modifier.fillMaxWidth()) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            step?.let {
                Box(Modifier.size(28.dp).background(KColors.Ink, CircleShape), contentAlignment = Alignment.Center) {
                    Text(it.toString(), color = KColors.Lime, fontSize = 13.sp, fontWeight = FontWeight.Black)
                }
                Spacer(Modifier.width(10.dp))
            }
            Column(Modifier.weight(1f)) {
                Text(title, color = KColors.Ink, fontSize = 17.sp, fontWeight = FontWeight.Bold, modifier = Modifier.semantics { heading() })
                subtitle?.let { Text(it, color = KColors.Muted, fontSize = 12.sp, lineHeight = 17.sp, modifier = Modifier.padding(top = 2.dp)) }
            }
            trailing()
        }
        Spacer(Modifier.height(14.dp))
        content()
    }
}

@Composable
internal fun PrimaryButton(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    enabled: Boolean = true,
    container: Color = KColors.Ink,
) {
    Button(
        onClick = onClick,
        enabled = enabled,
        shape = RoundedCornerShape(16.dp),
        colors = ButtonDefaults.buttonColors(
            containerColor = container,
            contentColor = Color.White,
            disabledContainerColor = Color(0xFFD9DBD2),
            disabledContentColor = Color(0xFF80857A),
        ),
        modifier = modifier.fillMaxWidth().height(54.dp),
    ) { Text(text, fontWeight = FontWeight.Bold, fontSize = 15.sp, textAlign = TextAlign.Center) }
}

@Composable
internal fun SecondaryButton(text: String, onClick: () -> Unit, modifier: Modifier = Modifier, enabled: Boolean = true, contentColor: Color = KColors.Ink) {
    OutlinedButton(
        onClick = onClick,
        enabled = enabled,
        shape = RoundedCornerShape(16.dp),
        border = BorderStroke(1.dp, if (enabled) KColors.Line else KColors.Line.copy(alpha = 0.5f)),
        colors = ButtonDefaults.outlinedButtonColors(containerColor = Color.White, contentColor = contentColor),
        modifier = modifier.fillMaxWidth().height(50.dp),
    ) { Text(text, fontWeight = FontWeight.Bold, textAlign = TextAlign.Center) }
}

@Composable
internal fun KChip(selected: Boolean, label: String, onClick: () -> Unit, modifier: Modifier = Modifier, enabled: Boolean = true) {
    FilterChip(
        selected = selected,
        onClick = onClick,
        enabled = enabled,
        label = { Text(label, fontWeight = FontWeight.SemiBold) },
        shape = RoundedCornerShape(50),
        colors = FilterChipDefaults.filterChipColors(
            containerColor = Color.White,
            labelColor = KColors.Ink,
            selectedContainerColor = KColors.Ink,
            selectedLabelColor = Color.White,
            disabledContainerColor = KColors.Canvas,
            disabledLabelColor = KColors.Faint,
        ),
        border = FilterChipDefaults.filterChipBorder(
            enabled = enabled,
            selected = selected,
            borderColor = KColors.Line,
            selectedBorderColor = KColors.Ink,
        ),
        modifier = modifier.padding(end = 8.dp),
    )
}

internal fun statusColors(status: String): Pair<Color, Color> = when (status.lowercase()) {
    "paid", "confirmed", "accepted" -> KColors.Success to KColors.SuccessSoft
    "cancelled", "rejected", "failed", "expired" -> KColors.Danger to KColors.DangerSoft
    "completed", "seated" -> KColors.Info to KColors.InfoSoft
    "pending", "partial" -> KColors.Warning to KColors.WarningSoft
    else -> KColors.Muted to Color(0xFFEFF0EC)
}

@Composable
internal fun StatusPill(status: String, modifier: Modifier = Modifier) {
    val (foreground, background) = statusColors(status)
    Text(
        status.replace('_', ' ').replaceFirstChar { it.uppercase() },
        color = foreground,
        fontSize = 11.sp,
        fontWeight = FontWeight.ExtraBold,
        modifier = modifier.background(background, RoundedCornerShape(50)).padding(horizontal = 10.dp, vertical = 5.dp),
    )
}

@Composable
internal fun ErrorBanner(message: String, onDismiss: () -> Unit, modifier: Modifier = Modifier) {
    Surface(modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp), color = KColors.DangerSoft) {
        Row(Modifier.padding(start = 14.dp, top = 6.dp, bottom = 6.dp, end = 4.dp), verticalAlignment = Alignment.CenterVertically) {
            Icon(Icons.Default.ErrorOutline, contentDescription = null, tint = KColors.Danger, modifier = Modifier.size(20.dp))
            Text(message, color = KColors.Danger, fontSize = 13.sp, lineHeight = 18.sp, modifier = Modifier.weight(1f).padding(horizontal = 10.dp, vertical = 6.dp))
            IconButton(onClick = onDismiss, modifier = Modifier.size(36.dp)) { Icon(Icons.Default.Close, contentDescription = "Dismiss message", tint = KColors.Danger, modifier = Modifier.size(18.dp)) }
        }
    }
}

@Composable
internal fun EmptyState(icon: ImageVector, title: String, message: String, modifier: Modifier = Modifier, action: (@Composable () -> Unit)? = null) {
    KCard(modifier.fillMaxWidth(), contentPadding = 28.dp) {
        Column(Modifier.fillMaxWidth(), horizontalAlignment = Alignment.CenterHorizontally) {
            Box(Modifier.size(56.dp).background(KColors.LimeSoft, CircleShape), contentAlignment = Alignment.Center) {
                Icon(icon, contentDescription = null, tint = KColors.Olive, modifier = Modifier.size(28.dp))
            }
            Text(title, fontWeight = FontWeight.Bold, fontSize = 16.sp, textAlign = TextAlign.Center, modifier = Modifier.padding(top = 12.dp))
            Text(message, color = KColors.Muted, fontSize = 13.sp, lineHeight = 18.sp, textAlign = TextAlign.Center, modifier = Modifier.padding(top = 5.dp))
            action?.let { Box(Modifier.padding(top = 16.dp)) { it() } }
        }
    }
}

@Composable
internal fun ProductImage(product: Product, modifier: Modifier, widthPx: Int = 480, heightPx: Int = 360) {
    val context = LocalContext.current
    if (product.image_url != null) {
        AsyncImage(
            model = remember(product.image_url) { ImageRequest.Builder(context).data(product.image_url).size(widthPx, heightPx).crossfade(true).build() },
            contentDescription = product.name,
            modifier = modifier.background(KColors.LimeSoft),
            contentScale = ContentScale.Crop,
        )
    } else {
        Box(modifier.background(KColors.LimeSoft), contentAlignment = Alignment.Center) {
            Text(product.name.take(1), color = KColors.Olive, fontSize = 34.sp, fontWeight = FontWeight.Bold)
        }
    }
}

/** "Add" until something is chosen, then a compact minus / count / plus control. */
@Composable
internal fun QuantityStepper(name: String, quantity: Int, canIncrease: Boolean, onChange: (Int) -> Unit, modifier: Modifier = Modifier) {
    if (quantity <= 0) {
        Button(
            onClick = { onChange(1) },
            enabled = canIncrease,
            shape = RoundedCornerShape(50),
            contentPadding = PaddingValues(horizontal = 14.dp),
            colors = ButtonDefaults.buttonColors(containerColor = KColors.Ink, contentColor = Color.White),
            modifier = modifier.height(36.dp).semantics { contentDescription = "Add $name" },
        ) {
            Icon(Icons.Default.Add, contentDescription = null, modifier = Modifier.size(16.dp))
            Spacer(Modifier.width(4.dp))
            Text(if (canIncrease) "Add" else "Sold out", fontSize = 13.sp, fontWeight = FontWeight.Bold)
        }
        return
    }
    Row(
        modifier.height(36.dp).clip(RoundedCornerShape(50)).background(KColors.LimeSoft),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        IconButton(onClick = { onChange(quantity - 1) }, modifier = Modifier.size(36.dp).semantics { contentDescription = "Decrease $name" }) {
            Icon(Icons.Default.Remove, contentDescription = null, modifier = Modifier.size(16.dp))
        }
        Text(quantity.toString(), fontWeight = FontWeight.Black, textAlign = TextAlign.Center, modifier = Modifier.widthIn(min = 22.dp))
        IconButton(onClick = { onChange(quantity + 1) }, enabled = canIncrease, modifier = Modifier.size(36.dp).semantics { contentDescription = "Increase $name" }) {
            Icon(Icons.Default.Add, contentDescription = null, modifier = Modifier.size(16.dp))
        }
    }
}
