package br.gov.pbh.sizem;

import android.content.SharedPreferences;
import android.content.pm.PackageInfo;
import android.os.Bundle;
import android.webkit.WebView;

import androidx.core.content.pm.PackageInfoCompat;

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
                publicarVersaoDoApk(webView);
            }
        });
    }

    /**
     * Publica a versao do APK instalado para a pagina remota.
     *
     * A tela inicial mostra por padrao a build publicada no servidor (data +
     * commit); dentro do app o que interessa saber tambem e qual APK esta no
     * aparelho, que so o lado nativo conhece. Injetar no onPageLoaded cobre
     * qualquer navegacao, inclusive a primeira.
     */
    private void publicarVersaoDoApk(WebView webView) {
        String versao = versionName();
        if (versao == null) {
            return;
        }
        webView.evaluateJavascript(
            "window.__sizemAppVersao='" + versao.replace("'", "") + "';"
                + "document.dispatchEvent(new CustomEvent('sizem:app-versao'));",
            null
        );
    }

    private String versionName() {
        try {
            return getPackageManager().getPackageInfo(getPackageName(), 0).versionName;
        } catch (Exception e) {
            return null;
        }
    }

    private void clearWebViewCacheOncePerVersion() {
        SharedPreferences prefs = getSharedPreferences(PREFS, MODE_PRIVATE);
        int lastCleared = prefs.getInt(KEY_CACHE_CLEARED_FOR, 0);
        int versionCode;
        try {
            // PackageInfoCompat, e nao getLongVersionCode() direto: o metodo so
            // existe a partir da API 28 e o minSdk e 23 — em Android 6.0–8.0 a
            // chamada direta estoura NoSuchMethodError (um Error, que este
            // catch de Exception nao pega) e o app fecharia no onCreate.
            PackageInfo info = getPackageManager().getPackageInfo(getPackageName(), 0);
            versionCode = (int) PackageInfoCompat.getLongVersionCode(info);
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