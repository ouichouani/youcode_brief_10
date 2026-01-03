<?php

namespace Controller;

require 'User.php';

use Exception;


class Admin extends User
{

    protected string $role = 'admin'; // override traveler with admin


    public function __construct($name, $email, $password, $confirm_password, $img = null)
    {
        return parent::__construct($name, $email, $password, $confirm_password, $img = null);
    }

    // user management 

    static function showTotalUsers(): int
    {
        if (isset($_SESSION["AuthUser"]) && $_SESSION["AuthUser"]["role"] == "admin") {
            $connection = Connection::get_instance()->get_PDO();
            $statement = $connection->query('SELECT count(*) as total_users FROM users');
            $users_number = $statement->fetch();
            return $users_number['total_users'];
        } else {
            return -1;
        }
    }

    public static function desactivateUser(int $id): void
    {
        try {
            if ($id <= 0) throw new Exception('invalid user id');
            if ( isset($_SESSION["AuthUser"]) && $_SESSION["AuthUser"]["role"] != "admin") throw new Exception('you don\'t have the permision to desactivate a user');

            $connection = Connection::get_instance()->get_PDO();
            $id = intval($id);

            if (!self::show($id)) throw new Exception('somthing wrong');

            $statement = $connection->prepare('UPDATE users SET active = 0 WHERE id = ?');
            $status = $statement->execute([$id]);

            if (!$status) throw new Exception('desactivation failed : ' . $statement->errorInfo()[2]);
            $_SESSION['success'][] = 'user desactivated successfully';
        } catch (Exception $e) {
            $_SESSION['error'][] = 'error : ' . $e->getMessage();
        }
    }

    public static function activateUser(int $id): void
    {
        try {
            if ($id <= 0) throw new Exception('invalid user id');
            if ( isset($_SESSION["AuthUser"]) && $_SESSION["AuthUser"]["role"] != "admin") throw new Exception('you don\'t have the permision to desactivate a user');

            $connection = Connection::get_instance()->get_PDO();
            $id = intval($id);

            if (!self::show($id)) throw new Exception('somthing wrong');

            $statement = $connection->prepare('UPDATE users SET active = 1 WHERE id = ?');
            $status = $statement->execute([$id]);

            if (!$status) throw new Exception('activation failed : ' . $statement->errorInfo()[2]);
            $_SESSION['success'][] = 'user activated successfully';
        } catch (Exception $e) {
            $_SESSION['error'][] = 'error : ' . $e->getMessage();
        }
    }
}


// add active to user table



    // public function showTotalAccommodations() {} // go to accomodation
    // public function showTotalBookings() {} // reservation
    // public function showRevenue() {} //?
    // public function showTopAccommodations() {} // go to accomodation
    // public function activateProperty() {} // go to accomodation
    // public function desactivateProperty() {} // go to accomodation