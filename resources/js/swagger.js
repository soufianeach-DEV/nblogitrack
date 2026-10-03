// Swagger UI sur la page /api/docs : il lit docs/openapi.yaml, servi par
// l'application.
import { SwaggerUIBundle } from 'swagger-ui-dist';
import 'swagger-ui-dist/swagger-ui.css';

const conteneur = document.getElementById('swagger');

SwaggerUIBundle({
    url: conteneur.dataset.specification,
    domNode: conteneur,
    deepLinking: true,
    // La cle saisie dans « Authorize » reste le temps de l'onglet.
    persistAuthorization: false,
});
