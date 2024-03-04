<?php

// Устанавливаем заголовки для разрешения CORS
header("Access-Control-Allow-Origin: *");

if (!empty($_SERVER['QUERY_STRING'])) { // проверяем наличие query string
    $username = $_GET['username'];

    $mysql = new mysqli("localhost", "root", "", "testdb");
    if ($mysql->connect_error) {
        echo json_encode('Error Number: ' . $mysql->connect_errno);
        exit;
    } else {
        $sql_query = "SELECT * FROM Users WHERE Login = '$username' ";
        $result_query = $mysql->query($sql_query);
        if ($result_query) {
            $row = $result_query->fetch_assoc();
            echo json_encode($row, JSON_UNESCAPED_UNICODE);
            exit;
        } else {
            echo json_encode("Error: ", $mysql->error);
            exit;
        }
    }
}

// Определение маршрутов
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI']; // сами endpoints

// if ($method === 'POST' && $requestUri === '/index.php/token') {
//     $secretKey = "6LdxW4gpAAAAAADaJUivpSImYHE2gfcPJWPlR7MI"; // секретный ключ reCAPTCHA
//     $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify'; // URL для проверки токена reCAPTCHA
//     $token = file_get_contents('php://input'); // Получаем тело HTTP-запроса, сразу json строка

//     // Данные для отправки на сервер reCAPTCHA для проверки
//     $data = array(
//         'secret' => $secretKey,
//         'response' => $token
//     );

//     $options = array(
//         'http' => array(
//             'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
//             'method'  => 'POST',
//             'content' => http_build_query($data)
//         )
//     );

//     $context  = stream_context_create($options);
//     $response = file_get_contents($verifyUrl, false, $context);
//     $result = json_decode($response); // не может преобразовать в json, да и вообще зачем это делать

//     if ($result->success) {
//         http_response_code(200);
//         echo 'Токен действителен';
//         exit;
//     } else {
//         http_response_code(400);
//         echo 'Токен недействителен';
//         exit;
//     }
// }

if ($method === 'GET' && $requestUri === '/index.php/users') {
    $mysql = new mysqli("localhost", "root", "", "testdb");
    if ($mysql->connect_error) {
        echo json_encode('Error Number: ' . $mysql->connect_errno);
        exit;
    } else {
        $sql_query = "SELECT LastName FROM Users";
        $result_query = $mysql->query($sql_query);
        if ($result_query) {
            $html_table = '<table>';

            while ($row = $result_query->fetch_assoc()) {
                $html_table .= '<tr><td>' . $row['LastName'] . '</td></tr>';
            }
            $html_table .= '</table>';
            echo $html_table;
            exit;
        } else {
            echo json_encode("Error: ", $mysql->error);
            exit;
        }
    }
}

if ($method === 'GET' && $requestUri === '/index.php/users/statistics') {
    $mysql = new mysqli("localhost", "root", "", "testdb");
    if ($mysql->connect_error) {
        echo json_encode('Error Number: ' . $mysql->connect_errno);
        exit;
    } else {
        $resultHtml = '';

        $sql_query = 'SELECT COUNT(*) FROM Users'; // количество записей в таблице
        $result_query = $mysql->query($sql_query);
        if ($result_query) {
            $row = $result_query->fetch_assoc();
            $resultHtml .= '<div> Количество записей в таблице: ' . $row['COUNT(*)'] . '</div>';
        } else {
            echo json_encode("Error: ", $mysql->error);
            exit;
        }

        $date_array = getdate();
        $begin_date = date("Y-m-d", mktime(0, 0, 0, $date_array['mon'], 1, $date_array['year']));
        $end_date = date("Y-m-d", mktime(0, 0, 0, $date_array['mon'] + 1, 0, $date_array['year']));
        $sql_query = "SELECT COUNT(*) FROM Users WHERE Created >= '$begin_date' AND Created <= '$end_date'"; // количество записей в таблице за последний месяц
        $result_query = $mysql->query($sql_query);
        if ($result_query) {
            $row = $result_query->fetch_assoc();
            $resultHtml .= '<div> Количество созданных записей за последний месяц: ' . $row['COUNT(*)'] . '</div>';
        } else {
            echo json_encode("Error: ", $mysql->error);
            exit;
        }

        $sql_query = 'SELECT * FROM Users ORDER BY Created DESC LIMIT 0,1'; // последняя созданная запись
        $result_query = $mysql->query($sql_query);
        if ($result_query) {
            $row = $result_query->fetch_assoc();
            $resultHtml .= '<div> Последняя созданная запись: ' . $row['Login'] . ' ' . $row['FirstName'] . ' ' . $row['LastName'] . '</div>';
        } else {
            echo json_encode("Error: ", $mysql->error);
            exit;
        }


        echo $resultHtml;
        exit;
    }
}

if ($method === 'POST' && $requestUri === '/index.php/user/register') {

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

        // сначала проверяем существование самого логина
        $sql_query = "SELECT COUNT(*) AS count FROM Users WHERE Login = '" . $requestData['login'] . "'";
        $result_query = $mysql->query($sql_query);
        $row = $result_query->fetch_assoc();
        if ($row['count'] > 0) { // такой логин уже существует
            http_response_code(403);
            exit;
        }

        $acceptRules = 0;
        if ($requestData['acceptRules'] === true) {
            $acceptRules = 1;
        }

        // хешируем пароль
        $hashedPassword = password_hash($requestData['password'], PASSWORD_BCRYPT);

        // добавляем запись в таблицу
        $sql_query = "INSERT INTO Users (FirstName, LastName, Email, Login, Password, AgeLimit, Gender, AcceptRules) 
        VALUES ('" . $requestData['firstName'] . "', '" . $requestData['lastName'] . "', '" . $requestData['email'] . "', '" . $requestData['login'] . "', '" . $hashedPassword . "', 
        '" . $requestData['age'] . "', '" . $requestData['gender'] . "', '" . $acceptRules . "')";

        $result_query = $mysql->query($sql_query);
        if (!$result_query) { // Если возникла ошибка при выполнении запроса
            echo json_encode("Ошибка выполнения запроса: " . $mysql->error);
            exit;
        }
    }

    $mysql->close();
    // отправляем в тело http ответа ассоциативный массив, преобразованный в json строку
    echo json_encode("Successful");
    exit;
}

if ($method === 'POST' && $requestUri === '/index.php/user/auth') {
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
