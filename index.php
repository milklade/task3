<?php
// Настройки подключения к базе данных
$db_host = 'localhost';
$db_name = 'u74052'; // Замените на ваш логин
$db_user = 'u74052'; // Замените на ваш логин
$db_pass = '693872'; // Замените на ваш пароль

// Инициализация переменных для формы и ошибок
$formData = [
    'name' => '',
    'phone' => '',
    'email' => '',
    'birthdate' => '',
    'gender' => '',
    'languages' => [],
    'bio' => '',
    'contract' => false
];

$errors = [];
$successMessage = '';
$showUsers = false;
$users = [];

// Подключение к базе данных
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

// Создание таблиц, если они не существуют
function createTables($pdo) {
    // Таблица для основной информации
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS applications (
            id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(150) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            email VARCHAR(100) NOT NULL,
            birthdate DATE NOT NULL,
            gender ENUM('male', 'female') NOT NULL,
            bio TEXT,
            contract_accepted BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    
    // Таблица языков программирования
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS programming_languages (
            id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(50) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    
    // Таблица связи между заявками и языками
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS application_languages (
            application_id INT(10) UNSIGNED NOT NULL,
            language_id INT(10) UNSIGNED NOT NULL,
            PRIMARY KEY (application_id, language_id),
            FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
            FOREIGN KEY (language_id) REFERENCES programming_languages(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    
    // Заполнение таблицы языков начальными значениями
    $languages = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskel', 'Clojure', 'Prolog', 'Scala', 'Go'];
    foreach ($languages as $lang) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO programming_languages (name) VALUES (?)");
        $stmt->execute([$lang]);
    }
}

createTables($pdo);

// Обработка запроса на просмотр пользователей
if (isset($_GET['show_users'])) {
    $showUsers = true;
    
    try {
        // Получаем всех пользователей
        $stmt = $pdo->query("
            SELECT a.id, a.name, a.phone, a.email, a.birthdate, a.gender, a.bio, a.created_at,
                   GROUP_CONCAT(pl.name SEPARATOR ', ') as languages
            FROM applications a
            LEFT JOIN application_languages al ON a.id = al.application_id
            LEFT JOIN programming_languages pl ON al.language_id = pl.id
            GROUP BY a.id
            ORDER BY a.created_at DESC
        ");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $errors['database'] = 'Ошибка при получении данных: ' . $e->getMessage();
    }
}

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['show_users'])) {
    // Получение и очистка данных
    $formData['name'] = trim($_POST['name'] ?? '');
    $formData['phone'] = trim($_POST['phone'] ?? '');
    $formData['email'] = trim($_POST['email'] ?? '');
    $formData['birthdate'] = trim($_POST['birthdate'] ?? '');
    $formData['gender'] = $_POST['gender'] ?? '';
    $formData['languages'] = $_POST['languages'] ?? [];
    $formData['bio'] = trim($_POST['bio'] ?? '');
    $formData['contract'] = isset($_POST['contract']);
    
    // Валидация данных
    // ФИО
    if (empty($formData['name'])) {
        $errors['name'] = 'Поле ФИО обязательно для заполнения';
    } elseif (strlen($formData['name']) > 150) {
        $errors['name'] = 'ФИО не должно превышать 150 символов';
    } elseif (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s]+$/u', $formData['name'])) {
        $errors['name'] = 'ФИО должно содержать только буквы и пробелы';
    }
    
    // Телефон
    if (empty($formData['phone'])) {
        $errors['phone'] = 'Поле телефона обязательно для заполнения';
    } elseif (!preg_match('/^\+?[0-9\s\-\(\)]{10,20}$/', $formData['phone'])) {
        $errors['phone'] = 'Введите корректный номер телефона';
    }
    
    // Email
    if (empty($formData['email'])) {
        $errors['email'] = 'Поле email обязательно для заполнения';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный email адрес';
    }
    
    // Дата рождения
    if (empty($formData['birthdate'])) {
        $errors['birthdate'] = 'Поле даты рождения обязательно для заполнения';
    } else {
        $birthdate = DateTime::createFromFormat('Y-m-d', $formData['birthdate']);
        if (!$birthdate || $birthdate->format('Y-m-d') !== $formData['birthdate']) {
            $errors['birthdate'] = 'Введите корректную дату рождения';
        }
    }
    
    // Пол
    if (empty($formData['gender'])) {
        $errors['gender'] = 'Укажите ваш пол';
    } elseif (!in_array($formData['gender'], ['male', 'female'])) {
        $errors['gender'] = 'Выбран недопустимый пол';
    }
    
    // Языки программирования
    if (empty($formData['languages'])) {
        $errors['languages'] = 'Выберите хотя бы один язык программирования';
    } else {
        // Проверка, что выбранные языки есть в базе
        $stmt = $pdo->prepare("SELECT id FROM programming_languages WHERE name = ?");
        foreach ($formData['languages'] as $lang) {
            $stmt->execute([$lang]);
            if (!$stmt->fetch()) {
                $errors['languages'] = 'Выбран недопустимый язык программирования';
                break;
            }
        }
    }
    
    // Биография
    if (empty($formData['bio'])) {
        $errors['bio'] = 'Поле биографии обязательно для заполнения';
    } elseif (strlen($formData['bio']) > 5000) {
        $errors['bio'] = 'Биография не должна превышать 5000 символов';
    }
    
    // Чекбокс контракта
    if (!$formData['contract']) {
        $errors['contract'] = 'Вы должны ознакомиться с контрактом';
    }
    
    // Если ошибок нет, сохраняем в базу данных
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Вставка основной информации
            $stmt = $pdo->prepare("
                INSERT INTO applications (name, phone, email, birthdate, gender, bio, contract_accepted)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $formData['name'],
                $formData['phone'],
                $formData['email'],
                $formData['birthdate'],
                $formData['gender'],
                $formData['bio'],
                $formData['contract'] ? 1 : 0
            ]);
            
            $applicationId = $pdo->lastInsertId();
            
            // Вставка выбранных языков программирования
            $stmt = $pdo->prepare("
                INSERT INTO application_languages (application_id, language_id)
                VALUES (?, (SELECT id FROM programming_languages WHERE name = ?))
            ");
            
            foreach ($formData['languages'] as $lang) {
                $stmt->execute([$applicationId, $lang]);
            }
            
            $pdo->commit();
            
            // Очистка формы после успешной отправки
            $formData = [
                'name' => '',
                'phone' => '',
                'email' => '',
                'birthdate' => '',
                'gender' => '',
                'languages' => [],
                'bio' => '',
                'contract' => false
            ];
            
            $successMessage = 'Данные успешно сохранены!';
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['database'] = 'Ошибка при сохранении данных: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Форма регистрации</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        
        h1 {
            text-align: center;
            color: #444;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        input[type="text"],
        input[type="tel"],
        input[type="email"],
        input[type="date"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .radio-group, .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .radio-option, .checkbox-option {
            display: flex;
            align-items: center;
        }
        
        .radio-option input, .checkbox-option input {
            margin-right: 10px;
        }
        
        .languages-select {
            height: 150px;
        }
        
        .btn-save {
            background-color:rgb(255, 0, 200);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            display: block;
            width: 100%;
            margin-top: 20px;
        }
        
        .btn-save:hover {
            background-color:rgb(255, 0, 234);
        }
        
        .btn-view {
            background-color:rgb(206, 90, 200);
            color: #333;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            display: block;
            width: 100%;
            margin-top: 10px;
        }
        
        .btn-view:hover {
            background-color:rgb(236, 117, 187);
        }
        
        .error {
            color: #d9534f;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .success {
            color: #5cb85c;
            font-size: 16px;
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
            background-color: #dff0d8;
            border-radius: 4px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #f2f2f2;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .back-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 15px;
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        
        .back-btn:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($showUsers): ?>
            <h1>Список зарегистрированных пользователей</h1>
            
            <?php if (!empty($users)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ФИО</th>
                            <th>Телефон</th>
                            <th>Email</th>
                            <th>Дата рождения</th>
                            <th>Пол</th>
                            <th>Языки программирования</th>
                            <th>Дата регистрации</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['id']) ?></td>
                                <td><?= htmlspecialchars($user['name']) ?></td>
                                <td><?= htmlspecialchars($user['phone']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><?= htmlspecialchars($user['birthdate']) ?></td>
                                <td><?= $user['gender'] === 'male' ? 'Мужской' : 'Женский' ?></td>
                                <td><?= htmlspecialchars($user['languages'] ?? 'Не указано') ?></td>
                                <td><?= htmlspecialchars($user['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Нет зарегистрированных пользователей.</p>
            <?php endif; ?>
            
            <a href="?" class="back-btn">Вернуться к форме</a>
            
        <?php else: ?>
            <h1>Форма регистрации</h1>
            
            <?php if (!empty($successMessage)): ?>
                <div class="success"><?= htmlspecialchars($successMessage) ?></div>
            <?php endif; ?>
            
            <?php if (isset($errors['database'])): ?>
                <div class="error"><?= htmlspecialchars($errors['database']) ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">ФИО:</label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($formData['name']) ?>">
                    <?php if (isset($errors['name'])): ?>
                        <div class="error"><?= htmlspecialchars($errors['name']) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="phone">Телефон:</label>
                    <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($formData['phone']) ?>">
                    <?php if (isset($errors['phone'])): ?>
                        <div class="error"><?= htmlspecialchars($errors['phone']) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($formData['email']) ?>">
                    <?php if (isset($errors['email'])): ?>
                        <div class="error"><?= htmlspecialchars($errors['email']) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="birthdate">Дата рождения:</label>
                    <input type="date" id="birthdate" name="birthdate" value="<?= htmlspecialchars($formData['birthdate']) ?>">
                    <?php if (isset($errors['birthdate'])): ?>
                        <div class="error"><?= htmlspecialchars($errors['birthdate']) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label>Пол:</label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="male" name="gender" value="male" <?= $formData['gender'] === 'male' ? 'checked' : '' ?>>
                            <label for="male">Мужской</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="female" name="gender" value="female" <?= $formData['gender'] === 'female' ? 'checked' : '' ?>>
                            <label for="female">Женский</label>
                        </div>
                    </div>
                    <?php if (isset($errors['gender'])): ?>
                        <div class="error"><?= htmlspecialchars($errors['gender']) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="languages">Любимый язык программирования:</label>
                    <select id="languages" name="languages[]" multiple class="languages-select">
                        <option value="Pascal" <?= in_array('Pascal', $formData['languages']) ? 'selected' : '' ?>>Pascal</option>
                        <option value="C" <?= in_array('C', $formData['languages']) ? 'selected' : '' ?>>C</option>
                        <option value="C++" <?= in_array('C++', $formData['languages']) ? 'selected' : '' ?>>C++</option>
                        <option value="JavaScript" <?= in_array('JavaScript', $formData['languages']) ? 'selected' : '' ?>>JavaScript</option>
                        <option value="PHP" <?= in_array('PHP', $formData['languages']) ? 'selected' : '' ?>>PHP</option>
                        <option value="Python" <?= in_array('Python', $formData['languages']) ? 'selected' : '' ?>>Python</option>
                        <option value="Java" <?= in_array('Java', $formData['languages']) ? 'selected' : '' ?>>Java</option>
                        <option value="Haskel" <?= in_array('Haskel', $formData['languages']) ? 'selected' : '' ?>>Haskel</option>
                        <option value="Clojure" <?= in_array('Clojure', $formData['languages']) ? 'selected' : '' ?>>Clojure</option>
                        <option value="Prolog" <?= in_array('Prolog', $formData['languages']) ? 'selected' : '' ?>>Prolog</option>
                        <option value="Scala" <?= in_array('Scala', $formData['languages']) ? 'selected' : '' ?>>Scala</option>
                        <option value="Go" <?= in_array('Go', $formData['languages']) ? 'selected' : '' ?>>Go</option>
                    </select>
                    <small>Для множественного выбора удерживайте Ctrl (Windows) или Command (Mac)</small>
                    <?php if (isset($errors['languages'])): ?>
                        <div class="error"><?= htmlspecialchars($errors['languages']) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="bio">Биография:</label>
                    <textarea id="bio" name="bio"><?= htmlspecialchars($formData['bio']) ?></textarea>
                    <?php if (isset($errors['bio'])): ?>
                        <div class="error"><?= htmlspecialchars($errors['bio']) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-option">
                        <input type="checkbox" id="contract" name="contract" <?= $formData['contract'] ? 'checked' : '' ?>>
                        <label for="contract">С контрактом ознакомлен(а)</label>
                    </div>
                    <?php if (isset($errors['contract'])): ?>
                        <div class="error"><?= htmlspecialchars($errors['contract']) ?></div>
                    <?php endif; ?>
                </div>
                
                <button type="submit" class="btn-save">Сохранить</button>
                <button type="button" onclick="window.location.href='?show_users=1'" class="btn-view">Просмотреть базу данных</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>