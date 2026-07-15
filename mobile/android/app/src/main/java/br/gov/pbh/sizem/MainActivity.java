package br.gov.pbh.sizem;

import android.content.SharedPreferences;
import android.os.Bundle;
import android.webkit.WebView;

import com.getcapacitor.BridgeActivity;
import com.getcapacitor.WebViewListener;

public class MainActivity extends BridgeActivity {

    private static final String PREFS = "sizem_campo";
    private static final String KEY_CACHE_CLEARED_FOR = "cache_cleared_for_version";

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        // Apos atualizar o APK, limpa o cache HTTP do WebView uma vez —
        // sem isso, HTML Blade antigo (ex.: faixa de homologacao) pode
        // continuar aparecendo mesmo com a producao ja corrigida.
        clearWebViewCacheOncePerVersion();

        // Loader adaptativo: esconde o splash assim que a pagina remota termina
        // de carregar, para o loader durar exatamente o tempo da carga (nao um
        // valor fixo). O failsafe de launchShowDuration cobre o caso de falha.
        this.bridge.addWebViewListener(new WebViewListener() {
            @Override
            public void onPageLoaded(WebView webView) {
                webView.evaluateJavascript(
                    "window.Capacitor?.Plugins?.SplashScreen?.hide?.();",
                    null
                );
            }
        });
    }

    private void clearWebViewCacheOncePerVersion() {
        SharedPreferences prefs = getSharedPreferences(PREFS, MODE_PRIVATE);
        int lastCleared = prefs.getInt(KEY_CACHE_CLEARED_FOR, 0);
        int versionCode;
        try {
            versionCode = (int) getPackageManager()
                .getPackageInfo(getPackageName(), 0)
                .getLongVersionCode();
        } catch (Exception e) {
            return;
        }
        if (lastCleared >= versionCode) {
            return;
        }
        if (this.bridge != null && this.bridge.getWebView() != null) {
            this.bridge.getWebView().clearCache(true);
        }
        prefs.edit().putInt(KEY_CACHE_CLEARED_FOR, versionCode).apply();
    }
}