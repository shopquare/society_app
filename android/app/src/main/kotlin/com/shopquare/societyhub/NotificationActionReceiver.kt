package com.shopquare.societyhub

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.widget.Toast

class NotificationActionReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        val visitorId = intent.getLongExtra("VISITOR_ID", 0L)
        if (visitorId == 0L) {
            GateAlertManager.stopAlarm(context)
            return
        }

        when (intent.action) {
            "com.shopquare.societyhub.ACTION_APPROVE_VISITOR" -> {
                Toast.makeText(context, "Approving visitor entry...", Toast.LENGTH_SHORT).show()
                GateAlertManager.submitApproval(context, visitorId, "Entered") { success, _ ->
                    val msg = if (success) "Visitor approved successfully!" else "Approval failed. Please check network."
                    Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()
                }
            }
            "com.shopquare.societyhub.ACTION_DENY_VISITOR" -> {
                Toast.makeText(context, "Denying visitor entry...", Toast.LENGTH_SHORT).show()
                GateAlertManager.submitApproval(context, visitorId, "Denied") { success, _ ->
                    val msg = if (success) "Visitor denied entry." else "Action failed. Please check network."
                    Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()
                }
            }
            else -> {
                GateAlertManager.stopAlarm(context)
            }
        }
    }
}
