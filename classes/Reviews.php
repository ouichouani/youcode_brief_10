<?php

require 'connection.php';

use Controller\Connection;

class Reviews
{

    private int $user_id;


    public function __construct(
        private int $accommodation_id,
        private int $rate
    ) {
        $this->accommodation_id = $accommodation_id;
        $this->rate = $rate;
    }

    public function create(): bool
    {
        try {

            // DATA VALIDATION
            if (!isset($_SESSION['AuthUser'])) throw new Exception('Unauthorized action');
            $this->user_id = $_SESSION['AuthUser']['id'];
            if ($this->rate < 1 || $this->rate > 5) throw new Exception('Invalid rating');
            if ($this->accommodation_id <= 0) throw new Exception('Invalid accommodation ID');
            if (self::previousTravelerReservationCount($this->user_id, $this->accommodation_id) <= 0) throw new Exception('You must have a previous reservation to leave a review');

            $connection = Connection::get_instance()->get_pdo();
            $stmt = $connection->prepare("INSERT INTO reviews (user_id, accommodation_id, rate) VALUES (?, ?, ?)");
            $status = $stmt->execute([$this->user_id, $this->accommodation_id, $this->rate]);
            if (!$status) throw new Exception('Failed to create review');

            return true;
        } catch (Exception $e) {
            $_SESSION['error'][] = $e->getMessage();
            return false;
        }
    }

    public static function calculateAvgRating($accommodation_id): int
    {
        try {
            $connection = Connection::get_instance()->get_PDO();
            $statement = $connection->prepare("SELECT avg(rate) FROM reviews WHERE accommodation_id = ?");
            $status = $statement->execute([$accommodation_id]);
            if (!$status) throw new Exception('Failed to retrieve average rating');

            return $statement->fetchColumn();

        } catch (Exception $e) {
            $_SESSION['error'][] = $e->getMessage();
            return -1;
        }
    }

    public static function previousTravelerReservationCount($user_id, $Accommodation_id): int
    {

        try {

            $connection = Connection::get_instance()->get_pdo();
            $statement = $connection->prepare("SELECT COUNT(*) FROM reservations WHERE user_id = ? AND accommodation_id = ? ");

            $status = $statement->execute([$user_id, $Accommodation_id]);
            if (!$status) throw new Exception('failed to retrieve reservation count');

            return $statement->fetchColumn();

            // EXCEPTION HANDLING
        } catch (Exception $e) {
            $_SESSION['error'][] = $e->getMessage();
            return -1;
        }
    }



}
