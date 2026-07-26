package com.example.data.catalogue

import com.example.core.model.Promotion

/**
 * Fetches the Home billboard promotions from the server. Returns null on any failure
 * (offline or error), in which case the app keeps its current local billboards — the
 * app is never left without promotions (server is only for syncing).
 */
interface RemoteBillboardSource {
    suspend fun fetch(): List<Promotion>?
}
