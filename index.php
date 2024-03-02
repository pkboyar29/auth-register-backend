<?php

// Устанавливаем заголовки для разрешения CORS
header("Access-Control-Allow-Origin: *");

// Определение маршрутов
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];

// Разбиваем URI на отдельные части
$parsedUri = parse_url($requestUri);
$path = $parsedUri['path'];

if ($method === 'POST' && $path === '/index.php/user/register') {

    // Получаем тело HTTP-запроса, сразу json строка
    $requestBody = file_get_contents('php://input');
    // Преобразуем JSON-строку в ассоциативный массив (за это отвечает второй параметр: associative)
    $requestData = json_decode($requestBody, true);

    // добавить в БД данные из json строки
    $mysql = new mysqli("localhost", "root", "", "testdb");
    if ($mysql->connect_error) {
        echo json_encode('Error Number: ' . $mysql->connect_errno);
        // echo json_encode('Error Description: ' . $mysql->connect_error);
        exit;
    } else {

        $acceptRules = 0;
        if ($requestData['acceptRules'] === true) {
            $acceptRules = 1;
        }

        // хешируем пароль
        $hashedPassword = password_hash($requestData['password'], PASSWORD_BCRYPT);

        $sql_query = "INSERT INTO Users (FirstName, LastName, Email, Login, Password, AgeLimit, Gender, AcceptRules) 
        VALUES ('" . $requestData['firstName'] . "', '" . $requestData['lastName'] . "', '" . $requestData['email'] . "', '" . $requestData['login'] . "', '" . $hashedPassword . "', 
        '" . $requestData['age'] . "', '" . $requestData['gender'] . "', '" . $acceptRules . "')";

        $result_query = $mysql->query($sql_query);
        if (!$result_query) { // Если возникла ошибка при выполнении запроса
            // ЖЕЛАТЕЛЬНО И ПРАВИЛЬНЕЕ ПОНИМАТЬ, ЧТО ОШИБКА ИЗ-ЗА ТОГО, ЧТО ЛОГИН УЖЕ СУЩЕСТВУЕТ, а то тут любая ошибка это код 403, а это типо из-за существующего логина
            // echo json_encode("Ошибка выполнения запроса: " . $mysql->error);
            http_response_code(403);
            exit;
        }
    }

    $mysql->close();
    // отправляем в тело http ответа ассоциативный массив, преобразованный в json строку
    echo json_encode("Successful");
    exit;
}

if ($method === 'POST' && $path === '/index.php/user/auth') {
    // отправить три http ответа: если все отлично / если логин неправилен / если пароль неправилен

    // Получаем тело HTTP-запроса, сразу json строка
    $requestBody = file_get_contents('php://input');
    // Преобразуем JSON-строку в ассоциативный массив (за это отвечает второй параметр: associative)
    $requestData = json_decode($requestBody, true);

    $mysql = new mysqli("localhost", "root", "", "testdb");
    if ($mysql->connect_error) {
        echo json_encode('Error Number: ' . $mysql->connect_errno);
        echo json_encode('Error Description: ' . $mysql->connect_error);
    } else {

        // ищем пользователя по логину
        $sql_query = "SELECT * FROM Users WHERE Login='" . $requestData['login'] . "'";
        $result_query = $mysql->query($sql_query);

        if ($result_query) {

            // если пользователь с таким логином не найден
            if ($result_query->num_rows == 0) {
                http_response_code(404);
                echo json_encode("Пользователь с таким логином не найден");
                exit;
            } else {
                // получаем данные о пользователе
                $user_data = $result_query->fetch_assoc();

                if (password_verify($requestData['password'], $user_data['Password'])) {
                    http_response_code(200);
                    echo json_encode("Успешная авторизация");
                    exit;
                } else {
                    http_response_code(403);
                    echo json_encode("Пароль неправильный");
                    exit;
                }
            }
        } else {
            // Если возникла ошибка при выполнении запроса
            echo json_encode("Ошибка выполнения запроса: " . $mysql->error);
        }
    }

    echo $requestBody;
}


$requestUri = $_SERVER['REQUEST_URI']; // часть URI, которая идет после протокола и server host, начинается со /
$uriSegments = explode('/', rtrim($requestUri, '/')); // получаем нумерованный массив со всеми частями URI

// http-запросы на получение имени по логину
if ($method === 'GET' && $uriSegments[count($uriSegments) - 2] === 'userinfo') {
    $login = end($uriSegments); // Получаем последний сегмент URI
    $userInfo = getUserInfoByLogin($login);
    echo json_encode($userInfo);
    exit;
}

function getUserInfoByLogin($login)
{
    $mysql = new mysqli("localhost", "root", "", "testdb");
    if ($mysql->connect_error) {
        echo json_encode('Error Number: ' . $mysql->connect_errno);
        echo json_encode('Error Description: ' . $mysql->connect_error);
    } else {
        // ищем имя пользователя по логину
        $sql_query = "SELECT FirstName, Theme FROM Users WHERE Login = '" . $login . "' ";
        $result_query = $mysql->query($sql_query);

        if ($result_query) {
            $userInfo = $result_query->fetch_assoc();
            return $userInfo;
        } else {
            echo json_encode("Ошибка выполнения запроса: " . $mysql->error);
            exit;
        }
    }
}

if ($method === 'POST' && $uriSegments[count($uriSegments) - 2] === 'changeTheme') {

    $login = end($uriSegments); // Получаем последний сегмент URI
    $themeBody = file_get_contents('php://input');
    $themeData = json_decode($themeBody, true);

    $mysql = new mysqli("localhost", "root", "", "testdb");
    if ($mysql->connect_error) {
        echo json_encode('Error Number: ' . $mysql->connect_errno);
        echo json_encode('Error Description: ' . $mysql->connect_error);
    } else {

        $sql_query = "UPDATE Users SET Theme = '" . $themeData['Theme'] . "' WHERE Login = '" . $login . "'";

        $result_query = $mysql->query($sql_query);
        if ($result_query) {
        } else {
            // Если возникла ошибка при выполнении запроса
            echo json_encode("Ошибка выполнения запроса: " . $mysql->error);
        }
    }

    $mysql->close();

    echo json_encode("Тема успешно изменена");
    exit;
}
