package com.example.core.notifications

import android.content.Context
import android.net.ConnectivityManager
import android.net.Network
import android.net.NetworkCapabilities
import android.net.NetworkRequest
import kotlinx.coroutines.channels.awaitClose
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.callbackFlow
import kotlinx.coroutines.flow.distinctUntilChanged

/** The device's internet-transport state, collapsed to the four cases we care about. */
enum class ConnectionState { NONE, WIFI, CELLULAR, BOTH }

/**
 * Observes connectivity via [ConnectivityManager.registerNetworkCallback] and
 * reports a coalesced [ConnectionState].
 *
 * Requires only ACCESS_NETWORK_STATE (added to the manifest by the integration
 * step; no runtime prompt). Construct manually with any [Context] — no Hilt.
 */
class ConnectivityObserver(context: Context) {

    private val connectivityManager: ConnectivityManager? =
        context.applicationContext.getSystemService(ConnectivityManager::class.java)

    /**
     * A [Flow] that emits the current [ConnectionState] immediately and then on
     * every network change, de-duplicated so identical states are not re-emitted.
     * The callback is unregistered when collection stops.
     */
    fun observe(): Flow<ConnectionState> = callbackFlow {
        val callback = object : ConnectivityManager.NetworkCallback() {
            override fun onAvailable(network: Network) {
                trySend(current())
            }

            override fun onLost(network: Network) {
                trySend(current())
            }

            override fun onCapabilitiesChanged(
                network: Network,
                networkCapabilities: NetworkCapabilities
            ) {
                trySend(current())
            }
        }

        val request = NetworkRequest.Builder()
            .addCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
            .build()

        // Emit the state at subscription time so collectors do not wait for a change.
        trySend(current())

        val manager = connectivityManager
        if (manager != null) {
            manager.registerNetworkCallback(request, callback)
            awaitClose { manager.unregisterNetworkCallback(callback) }
        } else {
            awaitClose { }
        }
    }.distinctUntilChanged()

    /**
     * A one-shot read of the current state.
     *
     * Scans the active networks that actually have internet capability so
     * simultaneous Wi-Fi + mobile data resolves to [ConnectionState.BOTH]. An
     * internet transport that is neither Wi-Fi nor cellular (e.g. Ethernet/VPN)
     * is reported as [ConnectionState.WIFI] since we only model four states.
     */
    @Suppress("DEPRECATION") // allNetworks: needed for accurate BOTH detection on minSdk 24.
    fun current(): ConnectionState {
        val manager = connectivityManager ?: return ConnectionState.NONE
        var wifi = false
        var cellular = false
        var otherInternet = false
        for (network in manager.allNetworks) {
            val caps = manager.getNetworkCapabilities(network) ?: continue
            if (!caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)) continue
            val isWifi = caps.hasTransport(NetworkCapabilities.TRANSPORT_WIFI)
            val isCellular = caps.hasTransport(NetworkCapabilities.TRANSPORT_CELLULAR)
            when {
                isWifi -> wifi = true
                isCellular -> cellular = true
                else -> otherInternet = true
            }
        }
        return when {
            wifi && cellular -> ConnectionState.BOTH
            wifi -> ConnectionState.WIFI
            cellular -> ConnectionState.CELLULAR
            otherInternet -> ConnectionState.WIFI
            else -> ConnectionState.NONE
        }
    }
}
