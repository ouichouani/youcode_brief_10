<?php


namespace Controller;

session_start();
require 'connection.php';
use Exception;
use Controller\Connection;

//USER INTERFACE

interface User_dependencies{
    public static function login(string $email ,string $password): array ;
    public static function logout(): bool ;
    public function regester(): array ;
}


// USER CLASS
class User implements User_dependencies
{
    protected string $name;
    protected string $email;
    protected string $role = 'traveler';
    protected ?string $password;
    protected ?string $confirm_password;
    protected ?string $img;

    public function __construct(string $name, string $email,string $password,string $confirm_password,string $img = null)
    {
        $this->name = $name;
        $this->email = $email;
        $this->password = $password;
        $this->confirm_password = $confirm_password;
        $this->img = $img;
    }

    public function get_role(): string
    {
        return $this->role;
    }

    public function regester(): array
    {

        try {

            $connection = Connection::get_instance()->get_PDO();
            $ERRORS = [];

            if (!preg_match('/^[ a-zA-Z0-9_-]{3,}$/', $this->name)) $ERRORS["name"] = 'name is required and contain at least 3 characters';
            if (!preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $this->email)) $ERRORS["email"] = 'email is invalid';
            if (!preg_match('/^[^\s]{8,}$/', $this->password)) $ERRORS["password"] = 'password is required and contain at least 8 characters';
            if ($this->confirm_password != $this->password) $ERRORS["confirm_password"] = 'confirmed password should be idontical with password';
            if ($this->img && !preg_match('/^+(\.jpg|\.jpeg|\.png)$/', $this->img)) $ERRORS["img"] = 'img is not valid';

            $row = $this->validate_email($this->email);

            if ($row) $ERRORS["email"] = 'email is already used';
            if (count($ERRORS)) throw new Exception('invalid data');
            $hashed_password = password_hash($this->password, PASSWORD_DEFAULT);

            if ($this->img) {
                $statment = $connection->prepare("INSERT INTO users (name , email , password , role , img) VALUES (? , ? , ? , ? , ?)");
                if (!$statment) throw new Exception('invalid statment');
                $status = $statment->execute([$this->name, $this->email, $hashed_password, $this->role, $this->img]);
                if (!$status) {
                    $errorinfo = $statment->errorInfo();
                    throw new Exception('SQL execution error: ' . $errorinfo[2]);
                }
            } else {
                $statment = $connection->prepare("INSERT INTO users (name , email , password , role) VALUES (? , ? , ? , ? )");
                if (!$statment) throw new Exception('invalid statment');
                $status = $statment->execute([$this->name, $this->email, $hashed_password, $this->role]);
                if (!$status) {
                    $errorinfo = $statment->errorInfo();
                    throw new Exception('SQL execution error: ' . $errorinfo[2]);
                }
            }


            //GET USER DATA
            $get_user_statment = $connection->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $get_user_statment->execute([$this->email]);
            $row_authuser = $get_user_statment->fetch();

            $_SESSION['AuthUser'] = $row_authuser;
            $_SESSION['success'][] = "user created with success";
            return $row_authuser; // return the created object 

        } catch (Exception $e) {
            $_SESSION['error'][] = $e->getMessage();
            return $ERRORS;
        }
    }

    public static function update(int $id, ?string $name = null, ?string $password = null, ?string $confirm_password = null, ?string $img = null): array
    {

        try {

            // PERMITION VALIDATION
            if ($_SESSION['AuthUser']['id'] != $id) throw new Exception('you don\'t have permition to update this user');

            $connection = Connection::get_instance()->get_PDO();
            $ERRORS = [];

            // CHECK IF USER EXISTS
            $id = intval($id);
            $statement = $connection->prepare('SELECT * FROM users WHERE id = ?');
            $statement->execute([$id]);
            $row = $statement->fetch();

            if (!$row) {
                $ERRORS["id"] = 'user is not found';
                throw new Exception('user is not found');
            }

            $queryparams = [];
            $params = [];

            if ($name) {
                if (!preg_match('/^[ a-zA-Z0-9_-]{3,}$/', $name)) $ERRORS["name"] = 'name is required and contain at least 3 characters';
                $queryparams[] = 'name = ?';
                $params[] = $name;
            }

            if ($password) {
                if (!preg_match('/^[^\s]{8,}$/', $password)) $ERRORS["password"] = 'password is required and contain at least 8 characters';
                if ($confirm_password != $password) $ERRORS["confirm_password"] = 'confirmed password should be idontical with password';
                $queryparams[] = 'password = ?';
                $params[] = $password;
            }

            if ($img) {
                if ($img && !preg_match('/^[a-zA-Z0-9_\-\.]+\.(jpg|jpeg|png)$/i', $img)) $ERRORS["img"] = 'img is not valid';
                $queryparams[] = 'img = ?';
                $params[] = $img;
            }

            $params[] = $id;

            if (!$name && !$password && !$img) throw new Exception('nothing to update');
            if (count($ERRORS)) throw new Exception('invalid data');

            //UPDATE USER DATA
            $statement = $connection->prepare('UPDATE users SET ' . implode(' ,', $queryparams) . ' WHERE id = ? ');
            $status = $statement->execute([...$params]);
            if (!$status) {
                $ERRORS['error'] = $statement->errorInfo()[2];
                throw new Exception('upadate failes');
            }

            //GET USER DATA
            $statement = $connection->prepare("SELECT * FROM users WHERE id = ?");
            $status = $statement->execute([$id]);

            if (!$status) {
                $ERRORS['error'] = $statement->errorInfo()[2];
                throw new Exception('fetching user failes');
            }

            $row_authuser = $statement->fetch();
            $_SESSION['AuthUser'] = $row_authuser;
            $_SESSION['success'][] = "user updated with success";
            return $row_authuser; // return the created object 

        } catch (Exception $e) {
            $_SESSION['error'][] = $e->getMessage();
            return $ERRORS;
        }
    }

    public static function show($id): array
    {
        try {

            if (!$_SESSION['AuthUser']['id'] == $id && !$_SESSION['AuthUser']['role'] == 'admin') throw new Exception('not allowed to aceess thoes info');
            $connection = Connection::get_instance()->get_PDO();
            $id = intval($id);
            $statement = $connection->prepare('SELECT * FROM users WHERE id = ?');
            $statement->execute([$id]);
            $row = $statement->fetch();
            if (!$row) throw new Exception('user not found');
            return $row;
        } catch (Exception $e) {
            $_SESSION['error'][] =  $e->getMessage();
            return [];
        }
    }

    public static function logout(): bool
    {
        unset($_SESSION['AuthUser']);
        $_SESSION['success'][] = 'log out with success';
        return true;
    }

    public static function login(string $email,string $password): array
    {
        try {

            $ERRORS = [];

            if (empty($email)) $ERRORS["email"] = 'email is required';
            if (!preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $email)) $ERRORS["email"] = 'email is invalid';

            if (empty($password)) $ERRORS["password"] = 'password is required';
            if (!preg_match('/^[^\s]{8,}$/', $password)) $ERRORS["password"] = 'password is required and contain at least 8 characters';

            if (count($ERRORS)) throw new Exception('invalid data');
            $row = self::validate_email($email);

            if (!$row) {
                $ERRORS['error'] = "email doesn't exists";
                throw new Exception('email doesn\'t exists');
            }

            if (!password_verify($password, $row['password'])) {
                $ERRORS['error'] = "password is not correct";
                throw new Exception('password is not correct');
            }

            $_SESSION['success'][] = 'user loged in successfuly';
            $_SESSION['AuthUser'] = $row;
            return $row;
        } catch (Exception $e) {
            $_SESSION['error'][] = $e->getMessage();
            return $ERRORS;
        }
    }

    protected static function validate_email(string $email): array
    {
        //EMAIL VERIFICATION
        $connection = Connection::get_instance()->get_PDO();
        $get_user_statment = $connection->prepare("SELECT * FROM users WHERE email = ? LIMIT 1 ");
        $get_user_statment->execute([$email]);
        $row = $get_user_statment->fetch();
        if ($row)  return $row;
        return [];
    }
}
