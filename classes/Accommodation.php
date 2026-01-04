<?php

namespace Controller;

session_start();

// require 'connection.php';

use Controller\Connection;
use DateTime;
use Exception;

class Accommodation
{

    private string $name;
    private int $host_id;
    private int $price;
    private string $city;
    private ?DateTime $available_dates;
    private ?string $img;
    private ?int $number_of_guests;


    public function __construct(string $name, int $price, string $city,  int $number_of_guests = 1 , ?DateTime $available_dates = null, ?string $img = null)
    {
        $this->name = $name;
        $this->price = $price;
        $this->city = $city;
        $this->host_id = $_SESSION['AuthUser']['id']; // host id
        $this->available_dates = $available_dates;
        $this->img = $img;
        $this->number_of_guests = $number_of_guests;
    }


    public static function show(int $id): ?array
    {
        try {

            if ($id <= 0) throw new Exception('invalid accommodation id');
            $connection = Connection::get_instance()->get_pdo();
            $stmt = $connection->prepare("SELECT * FROM accommodations WHERE id = ?");
            $status = $stmt->execute([$id]);

            if (!$status) throw new Exception('Failed to retrieve accommodation');
            $accommodation = $stmt->fetch();

            if (!$accommodation) throw new Exception('Accommodation not found');
            return $accommodation;
        } catch (Exception $e) {
            $_SESSION['error'][] = $e->getMessage();
            return null;
        }
    }

    public static function index(): array
    {
        try {
            $connection = Connection::get_instance()->get_pdo();
            $stmt = $connection->prepare("SELECT * FROM Accommodations");
            $status = $stmt->execute();

            if (!$status) throw new Exception('Failed to retrieve accommodations');
            return $stmt->fetchAll();
        } catch (Exception $e) {
            $_SESSION['error'][] = $e->getMessage();
            return [];
        }
    }

    public function create(): array
    {
        $ERRORS = [];
        try {
            $connection = Connection::get_instance()->get_pdo();

            // PRICE VALIDATION
            if ($this->price <= 0) $ERRORS[] = "Price must be positive and greater than zero";

            // HOST VALIDATION
            if ($this->host_id <= 0) $ERRORS[] = "Host is not valid";

            $host = intval($this->host_id);
            $statement = $connection->prepare('SELECT * FROM users WHERE id = ?');
            $statement->execute([$host]);
            $user = $statement->fetch();
            if (!$user) $ERRORS[] = "Host is not valid";

            // CHECK FOR ERRORS
            if (count($ERRORS)) throw new Exception('invalid data : ' . implode(', ', $ERRORS));

            // CREATE ACCOMMODATION
            if ($this->available_dates) {
                $stmt = $connection->prepare("INSERT INTO accommodations (name, price, city , number_of_guests, host_id, available_dates, img) VALUES (?, ?, ?, ?, ?, ?)");
                $status = $stmt->execute([$this->name, $this->price, $this->city, $this->number_of_guests, $this->host_id, $this->available_dates->format('Y-m-d H:i:s'), $this->img]);
                if (!$status) throw new Exception('Failed to create accommodation');
            } else {
                $stmt = $connection->prepare("INSERT INTO accommodations (name, price, city, number_of_guests, host_id, img) VALUES (?, ?, ?, ?, ?, ?)");
                $status = $stmt->execute([$this->name, $this->price, $this->city, $this->number_of_guests, $this->host_id, $this->img]);
                if (!$status) throw new Exception('Failed to create accommodation');
            }

            // RETRIEVE ACCOMMODATION
            $stmt = $connection->prepare("SELECT * FROM accommodations WHERE id = ?");
            $status = $stmt->execute([$connection->lastInsertId()]);
            if (!$status) throw new Exception('Failed to retrieve accommodation');
            $_SESSION['success'][] = 'Accommodation created successfully';
            return $stmt->fetch();
        } catch (Exception $e) {
            // Handle exception
            $_SESSION['error'][] = $e->getMessage();
            return $ERRORS;
        }
    }

    public static function update(int $id, string $name, ?int $price = null, ?string $city = null , int $number_of_guests = 1, ?DateTime $available_dates = null, ?string $img = null): bool|array
    {
        $ERRORS = [];
        try {
            $id = intval($id);
            if ($id <= 0) throw new Exception('invalid accommodation id');

            $connection = Connection::get_instance()->get_pdo();

            self::show($id); // to check if accommodation exists

            $queryparams = [];
            $params = [];

            if ($name) {
                if (!preg_match('/^[ a-zA-Z0-9_-]{3,}$/', $name)) $ERRORS["name"] = 'name is required and contain at least 3 characters';
                $queryparams[] = 'name = ?';
                $params[] = $name;
            }

            if ($price) {
                if (!preg_match('/^[0-9]+(\.[0-9]{1,2})?$/', $price)) $ERRORS["price"] = 'price is required and must be a valid number';
                $queryparams[] = 'price = ?';
                $params[] = $price;
            }

            if ($img) {
                if ($img && !preg_match('/^[a-zA-Z0-9_\-\.]+\.(jpg|jpeg|png)$/i', $img)) $ERRORS["img"] = 'img is not valid';
                $queryparams[] = 'img = ?';
                $params[] = $img;
            }

            if ($city) {
                if ($city && !preg_match('/^[a-zA-Z0-9_\-\.]+$/i', $city)) $ERRORS["city"] = 'city is not valid';
                $queryparams[] = 'city = ?';
                $params[] = $city;
            }

            if ($available_dates) {
                if ($available_dates && !($available_dates instanceof DateTime)) $ERRORS["available_dates"] = 'available_dates is not valid';
                $queryparams[] = 'available_dates = ?';
                $params[] = $available_dates ? $available_dates->format('Y-m-d H:i:s') : null;
            }
            
            if ($number_of_guests != null) {
                if ($number_of_guests < 1) $ERRORS["number_of_guests"] = 'number_of_guests is not valid';
                $queryparams[] = 'number_of_guests = ?';
                $params[] = $number_of_guests;
            }

            $params[] = $id;
            if (!$name && !$price && !$img && !$city && !$available_dates && !$number_of_guests) throw new Exception('nothing to update');




            $stmt = $connection->prepare("UPDATE Accommodations SET " . implode(", ", $queryparams) . " WHERE id = ?");
            $status = $stmt->execute([...$params]);

            if (!$status) throw new Exception('Failed to update accommodation');
            $_SESSION['success'][] = 'accommodation updated successfully';
            return true ;

        } catch (Exception $e) {
            $_SESSION['error'][] = $e->getMessage();
            return $ERRORS ;
        }
    }

    public static function delete(int $id): bool
    {
        try {
            if ($id <= 0) throw new Exception('invalid accommodation id');

            $connection = Connection::get_instance()->get_pdo();

            self::show($id); // to check if accommodation exists

            $stmt = $connection->prepare("DELETE FROM Accommodations WHERE id = ?");
            $status = $stmt->execute([$id]);

            if (!$status) throw new Exception('Failed to delete accommodation');
            $_SESSION['success'][] = 'accommodation deleted successfully';
            return true;

        } catch (Exception $e) {
            $_SESSION['error'][] = $e->getMessage();
            return false;
        }
    }

    public static function showDisponibleAccommodation(): array
    {

        try {
            // CHECK USER ROLE
            if (!isset($_SESSION['AuthUser']) || $_SESSION['AuthUser']['role'] !== 'admin') throw new Exception('only Admins can view accommodations');

            $connection = Connection::get_instance()->get_pdo();
            $stmt = $connection->prepare("SELECT * FROM accommodations WHERE disponible = 1");
            $status = $stmt->execute();
            if (!$status) throw new Exception('Failed to retrieve accommodations');
            return $stmt->fetchAll();
        } catch (Exception $e) {
            $_SESSION['error'][] = $e->getMessage();
            return [];
        }
    }

    // view the revenue generated, see the 10 most profitable accommodations.
    public static function showTopAccommodations(): array
    {

        try {
            // CHECK USER ROLE
            if (!isset($_SESSION['AuthUser']) || $_SESSION['AuthUser']['role'] !== 'admin') throw new Exception('only Admins can view accommodations');

            $connection = Connection::get_instance()->get_pdo();
            $stmt = $connection->prepare("SELECT A.id , A.name , A.price ,A.img , A.host_id , count(R.id) * A.price as total_revenue 
            FROM Accommodations A INNER JOIN Reservations R 
            ON A.id = R.Accommodation_id 
            GROUP BY A.id , A.name , A.price ,A.img , A.host_id 
            ORDER BY total_revenue DESC LIMIT 10");

            $status = $stmt->execute();
            if (!$status) throw new Exception('Failed to retrieve accommodations');
            return $stmt->fetchAll();
        } catch (Exception $e) {
            $_SESSION['error'][] = $e->getMessage();
            return [];
        }
    }

    // see the number of accommodations
    public static function showTotalAccommodations(): int
    {

        try {
            // CHECK USER ROLE
            if (!isset($_SESSION['AuthUser']) || $_SESSION['AuthUser']['role'] !== 'admin') throw new Exception('only Admins can view accommodations number');

            $connection = Connection::get_instance()->get_pdo();
            $stmt = $connection->prepare("SELECT COUNT(*) FROM accommodations");
            $status = $stmt->execute();
            if (!$status) throw new Exception('Failed to retrieve accommodations number');
            return $stmt->fetchColumn();
        } catch (Exception $e) {
            $_SESSION['error'][] = $e->getMessage();
            return -1;
        }
    }

    // desactivate an accommodation
    public static function desactivateProperty(int $id): void
    {
        try {
            if ($id <= 0) throw new Exception('invalid accommodation id');
            if (!isset($_SESSION["AuthUser"]) || $_SESSION["AuthUser"]["role"] != "admin") throw new Exception('you don\'t have the permision to desactivate an accommodation');

            $connection = Connection::get_instance()->get_PDO();
            $id = intval($id);

            if (!self::show($id)) throw new Exception('somthing wrong');

            $statement = $connection->prepare('UPDATE Accommodations SET active = 0 WHERE id = ?');
            $status = $statement->execute([$id]);

            if (!$status) throw new Exception('desactivation failed : ' . $statement->errorInfo()[2]);
            $_SESSION['success'][] = 'accommodation desactivated successfully';
        } catch (Exception $e) {
            $_SESSION['error'][] = 'error : ' . $e->getMessage();
        }
    }

    // activate an accommodation
    public static function activateProperty(int $id): void
    {
        try {
            if ($id <= 0) throw new Exception('invalid accommodation id');
            if (!isset($_SESSION["AuthUser"]) || $_SESSION["AuthUser"]["role"] != "admin") throw new Exception('you don\'t have the permision to activate an accommodation');

            $connection = Connection::get_instance()->get_PDO();
            $id = intval($id);

            if (!self::show($id)) throw new Exception('somthing wrong');


            $statement = $connection->prepare('UPDATE Accommodations SET active = 1 WHERE id = ?');
            $status = $statement->execute([$id]);

            if (!$status) throw new Exception('activation failed : ' . $statement->errorInfo()[2]);
            $_SESSION['success'][] = 'accommodation activated successfully';
        } catch (Exception $e) {
            $_SESSION['error'][] = 'error : ' . $e->getMessage();
        }
    }

    // search by price range
    public static function searchByPrice ( float $min = 1 , float $max = 10000): array
    {
        try {
            // CHECK USER ROLE
            if (!isset($_SESSION['AuthUser'])) throw new Exception('only Auth users can view accommodations');

            $connection = Connection::get_instance()->get_pdo();
            $stmt = $connection->prepare("SELECT * FROM Accommodations WHERE price BETWEEN ? AND ?");
            $status = $stmt->execute([$min, $max]);
            if (!$status) throw new Exception('Failed to retrieve accommodations');
            return $stmt->fetchAll();
        } catch (Exception $e) {
            $_SESSION['error'][] = $e->getMessage();
            return [];
        }
    }
    // search by availability dates
    public static function searchByavailabilitydates (): array
    {
        try {
            // CHECK USER ROLE
            if (!isset($_SESSION['AuthUser'])) throw new Exception('only Auth users can view accommodations');

            $connection = Connection::get_instance()->get_pdo();
            $stmt = $connection->prepare("SELECT * FROM Accommodations WHERE availability_dates IS NULL");
            $status = $stmt->execute();
            if (!$status) throw new Exception('Failed to retrieve accommodations');
            return $stmt->fetchAll();
        } catch (Exception $e) {
            $_SESSION['error'][] = $e->getMessage();
            return [];
        }
    }

    // search by number of guests
    public static function searchByNumberGuests(int $minGuests, int $maxGuests): array
    {
        try {
            // CHECK USER ROLE
            if (!isset($_SESSION['AuthUser'])) throw new Exception('only Auth users can view accommodations');

            $connection = Connection::get_instance()->get_pdo();
            $stmt = $connection->prepare("SELECT * FROM Accommodations WHERE number_of_guests BETWEEN ? AND ?");

            $status = $stmt->execute([$minGuests, $maxGuests]);
            if (!$status) throw new Exception('Failed to retrieve accommodations');
            return $stmt->fetchAll();
        } catch (Exception $e) {
            $_SESSION['error'][] = $e->getMessage();
            return [];
        }
    }

    //create favorite
    public static function createFavorite(int $Accommodation_id): void
    {
        try {
            if (!isset($_SESSION["AuthUser"])) throw new Exception('you must be logged in to add a favorite');
            $user_id = $_SESSION["AuthUser"]["id"];

            $connection = Connection::get_instance()->get_PDO();

            //
            $statement = $connection->prepare('SELECT * FROM favorites WHERE user_id = ? AND Accommodation_id = ?');
            $status = $statement->execute([$user_id , $Accommodation_id]);
            if($statement->fetch()) throw new Exception('accommodation already in favorites');

            $statement = $connection->prepare('INSERT INTO favorites (user_id , Accommodation_id) VALUES (? , ?)');
            $status = $statement->execute([$user_id , $Accommodation_id]);

            if (!$status) throw new Exception('adding to favorites failed : ' . $statement->errorInfo()[2]);
            $_SESSION['success'][] = 'accommodation added to favorites successfully';
        } catch (Exception $e) {
            $_SESSION['error'][] = 'error : ' . $e->getMessage();
        }
    }

    // delete favorite
    public static function removeFavorites(int $Accommodation_id): void
    {
        try {
            if (!isset($_SESSION["AuthUser"])) throw new Exception('you must be logged in to remove a favorite');
            $user_id = $_SESSION["AuthUser"]["id"];

            $connection = Connection::get_instance()->get_PDO();
            $statement = $connection->prepare('SELECT * FROM favorites WHERE user_id = ? AND Accommodation_id = ?');
            $status = $statement->execute([$user_id , $Accommodation_id]);
            if(!$statement->fetch()) throw new Exception('accommodation not in favorites');

            $statement = $connection->prepare('DELETE FROM favorites WHERE user_id = ? AND Accommodation_id = ?');
            $status = $statement->execute([$user_id , $Accommodation_id]);

            if (!$status) throw new Exception('removing from favorites failed : ' . $statement->errorInfo()[2]);
            $_SESSION['success'][] = 'accommodation removed from favorites successfully';
        } catch (Exception $e) {
            $_SESSION['error'][] = 'error : ' . $e->getMessage();
        }
    }

    //show favorites
    public static function showFavorites(): array
    {
        try {
            if (!isset($_SESSION["AuthUser"])) throw new Exception('you must be logged in to view favorites');
            $user_id = $_SESSION["AuthUser"]["id"];

            $connection = Connection::get_instance()->get_PDO();

            $statement = $connection->prepare('SELECT DISTINCT A.* FROM Accommodations A INNER JOIN favorites F ON A.id = F.Accommodation_id WHERE F.user_id = ?');
            $status = $statement->execute([$user_id]);

            if (!$status) throw new Exception('retrieving favorites failed : ' . $statement->errorInfo()[2]);
            return $statement->fetchAll();
        } catch (Exception $e) {
            $_SESSION['error'][] = 'error : ' . $e->getMessage();
            return [];
        }
    }

}
