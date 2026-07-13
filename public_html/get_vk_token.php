// Временный скрипт для получения токена
// Сохраните как get_vk_token.php и запустите из браузера

$clientId = 'ВАШ_CLIENT_ID'; // ID вашего standalone приложения
$redirectUri = 'https://oauth.vk.com/blank.html';
$scope = 'messages,offline'; // Права доступа

$authUrl = 'https://oauth.vk.com/authorize?' . http_build_query([
    'client_id' => $clientId,
    'display' => 'page',
    'redirect_uri' => $redirectUri,
    'scope' => $scope,
    'response_type' => 'token',
    'v' => '5.131',
]);

echo "<a href='{$authUrl}' target='_blank'>Получить токен VK</a>";
echo "<br>После авторизации скопируйте access_token из URL (часть после #access_token=)";