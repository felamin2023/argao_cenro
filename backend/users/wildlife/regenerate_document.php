<?php

declare(strict_types=1);
// regenerate_document.php (deprecated stub)
// Wildlife document generation has been moved into:
//   backend/users/update_application.php :: regenerate_wildlife_application_form()
// The standalone endpoint is intentionally disabled to avoid duplication.
session_start();
header('Content-Type: application/json');

http_response_code(410);
echo json_encode([
    'ok' => false,
    'error' => 'Deprecated endpoint. Use update_application.php which handles regeneration.'
]);
exit();
