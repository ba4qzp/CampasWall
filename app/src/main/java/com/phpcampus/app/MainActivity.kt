package com.phpcampus.app

import android.app.AlertDialog
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.text.InputType
import android.widget.Button
import android.widget.EditText
import android.widget.LinearLayout
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import org.json.JSONObject
import java.net.URL
import kotlin.concurrent.thread

class MainActivity : AppCompatActivity() {
    val CURRENT_VERSION_CODE = 1 
    lateinit var sp: android.content.SharedPreferences

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        sp = getSharedPreferences("app_config", Context.MODE_PRIVATE)
        
        val layout = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(50, 100, 50, 50)
        }

        val textView = TextView(this).apply { 
            text = "欢迎使用 PHPCampus 原生端\n\n点击下方按钮连接你的站点。" 
            textSize = 18f
        }
        
        val btnConnect = Button(this).apply {
            text = "连接 / 更改站点"
            setOnClickListener { showInputDialog(textView) }
        }

        layout.addView(textView)
        layout.addView(btnConnect)
        setContentView(layout)

        val savedUrl = sp.getString("base_url", "")
        if (savedUrl != "") {
            textView.text = "上次连接的站点：\n$savedUrl\n\n正在检查更新..."
            checkVersionUpdate(savedUrl, textView)
        }
    }

    private fun showInputDialog(textView: TextView) {
        val input = EditText(this).apply {
            inputType = InputType.TYPE_CLASS_TEXT or InputType.TYPE_TEXT_VARIATION_URI
            hint = "输入网址，如 http://phpschool.gt.tc"
        }
        AlertDialog.Builder(this)
            .setTitle("连接站点")
            .setView(input)
            .setPositiveButton("连接") { _, _ ->
                var url = input.text.toString().trim()
                if (!url.startsWith("http")) url = "http://$url"
                if (url.endsWith("/")) url = url.dropLast(1)
                
                sp.edit().putString("base_url", url).apply()
                checkVersionUpdate(url, textView)
            }
            .setCancelable(false)
            .show()
    }

    private fun checkVersionUpdate(baseUrl: String, textView: TextView) {
        thread {
            try {
                val apiUrl = "$baseUrl/api.php?action=check_app_version&v_code=$CURRENT_VERSION_CODE"
                val response = URL(apiUrl).readText()
                val json = JSONObject(response)
                
                runOnUiThread {
                    if (json.optBoolean("update", false)) {
                        AlertDialog.Builder(this)
                            .setTitle("🚀 发现新版本")
                            .setMessage("最新版本：" + json.getString("new_version") + "\n\n" + json.getString("msg"))
                            .setPositiveButton("去下载") { _, _ ->
                                val intent = Intent(Intent.ACTION_VIEW, Uri.parse(json.getString("download_url")))
                                startActivity(intent)
                            }
                            .setCancelable(false)
                            .show()
                    } else {
                        textView.text = "站点连接成功！\n已是最新版本。\n\n(原生列表待开发)"
                    }
                }
            } catch (e: Exception) {
                runOnUiThread { 
                    textView.text = "已连接：$baseUrl\n\n(未检测到 api.php 或无网络)"
                }
            }
        }
    }
}
