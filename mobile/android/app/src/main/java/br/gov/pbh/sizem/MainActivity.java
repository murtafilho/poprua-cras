package br.gov.pbh.sizem;

import android.os.Bundle;
import android.webkit.WebView;

import com.getcapacitor.BridgeActivity;
import com.getcapacitor.WebViewListener;

public class MainActivity extends BridgeActivity {

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

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
}
