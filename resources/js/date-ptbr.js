// Date picker pt-BR — forca exibicao dd/mm/yyyy independente do locale do navegador.
//
// O input nativo datetime-local/date renderiza no formato do SO/navegador do
// usuario (em maquinas em ingles sai mm/dd/yyyy). O lang="pt-BR" da pagina NAO
// controla isso. Este modulo aplica Flatpickr nos inputs marcados com
// .js-date-ptbr: exibe dd/mm/aaaa (altInput) e mantem o input original com o
// valor ISO exigido pelo backend (date_format:Y-m-d\TH:i).
//
// No celular (uso principal em campo), Flatpickr cai no picker nativo do
// aparelho — que num telefone BR ja e dd/mm — preservando a UX nativa.
import flatpickr from "flatpickr";
import { Portuguese } from "flatpickr/dist/l10n/pt.js";
import "flatpickr/dist/flatpickr.min.css";

flatpickr.localize(Portuguese);

export function initDatePtBr(root = document) {
    root.querySelectorAll("input.js-date-ptbr").forEach(function (el) {
        if (el._flatpickr) {
            return;
        }
        const isDateTime = (el.getAttribute("type") || "").toLowerCase() === "datetime-local";
        flatpickr(el, {
            locale: Portuguese,
            // Ancora o calendario no proprio campo (nao no <body>). Sem isso, neste
            // form multi-abas o calendario fica solto no fim do body e sobrepoe a
            // aplicacao como um retangulo no rodape ao trocar de aba.
            static: true,
            enableTime: isDateTime,
            time_24hr: true,
            // O input original (submetido) mantem o formato ISO exigido pelo backend.
            dateFormat: isDateTime ? "Y-m-d\\TH:i" : "Y-m-d",
            // O input visivel mostra dd/mm/aaaa.
            altInput: true,
            altFormat: isDateTime ? "d/m/Y H:i" : "d/m/Y",
        });
    });
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => initDatePtBr());
} else {
    initDatePtBr();
}
