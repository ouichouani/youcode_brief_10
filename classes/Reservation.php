<?php

require 'connection.php';
require 'Mailer.php';

use Controller\Connection;
use Controller\Mail;


class Reservation
{


    private int $traveler_id;
    private int $Accommodation_id;
    private DateTime $start_dates;
    private DateTime $end_dates;



    public function __construct(int $Accommodation_id, DateTime $start_dates, DateTime $end_dates)
    {
        $this->traveler_id = $_SESSION['AuthUser']['id'];
        $this->Accommodation_id = $Accommodation_id;
        $this->start_dates = $start_dates;
        $this->end_dates = $end_dates;
    }

    public function book(): void
    {

        $connection = Connection::get_instance()->get_PDO();
        $connection->beginTransaction();

        try {


            // DATA VALIDATION
            $this->Accommodation_id <= 0 && throw new Exception("Invalid accommodation ID");
            if (!isset($_SESSION['user'])) throw new Exception("Unauthorized action");
            if (!$this->start_dates instanceof DateTime || $this->start_dates < new DateTime()) throw new Exception("Invalid date");
            if ($this->end_dates <= $this->start_dates) throw new Exception("End date must be after start date");


            // CHECK FOR EXISTING RESERVATION
            $statement = $connection->prepare('SELECT * FROM reservations WHERE traveler_id = ? AND accommodation_id = ?');
            $statement->execute([$this->traveler_id, $this->Accommodation_id]);
            $existingReservation = $statement->fetch();
            if ($existingReservation) throw new Exception("Reservation already exists");


            // CHECK ACCOMMODATION EXISTENCE
            $statement = $connection->prepare('SELECT * FROM accommodations WHERE id = ?');
            $statement->execute([$this->Accommodation_id]);
            $accommodation = $statement->fetch();
            if (!$accommodation) throw new Exception("Accommodation not found");


            // CHECK IF ACCOMMODATION IS ACTIVE
            if ($accommodation['active'] == 0) throw new Exception("Accommodation not available");


            // BLOCK ANY SIMULTANEOUS DOUBLE BOOKINGS
            if (!self::validateDate($this->start_dates, $this->end_dates)) throw new Exception("Invalid reservation dates");


            // CREATE RESERVATION
            $statement = $connection->prepare("INSERT INTO reservations (traveler_id, accommodation_id , start_dates , end_dates) VALUES (?, ? , ? , ?)");
            $status = $statement->execute([$this->traveler_id, $this->Accommodation_id, $this->start_dates, $this->end_dates]);
            $last_id = $connection->lastInsertId();
            if (!$status) throw new Exception("Failed to book accommodation");


            // FETCH THE RESERVATION DATA
            $reservation = $connection->prepare("SELECT * FROM reservations WHERE id = ?");
            $reservation->execute([$last_id]);
            $reservation = $reservation->fetch();


            // FETCH THE TRAVELER DATA
            $traveler = $_SESSION['AuthUser'];
            $host = $connection->prepare('SELECT u.* FROM users WHERE id = ?');
            $host->execute([$accommodation['host_id']]);
            $host = $host->fetch();


            // NOTIFY HOST AND TRAVELER
            self::notifiedHost($traveler, $host, $accommodation, $reservation);
            self::notifiedTraveler($traveler, $host, $accommodation, $reservation);

            $connection->commit();
        } catch (Exception $e) {

            $connection->rollBack();
            $_SESSION['error'][] = "Error booking accommodation: " . $e->getMessage();
        }
    }

    public function cancel(int $id): void
    {
        $connection = Connection::get_instance()->get_PDO();
        $connection->beginTransaction();

        try {

            if ($_SESSION['user']['role'] !== 'admin' && $_SESSION['user']['id'] !== $this->traveler_id) throw new Exception("Unauthorized action");


            // CHECK IF RESERVATION EXISTS
            $statement = $connection->prepare('SELECT * FROM reservations WHERE traveler_id = ? AND accommodation_id = ? AND id = ?');
            $statement->execute([$this->traveler_id, $this->Accommodation_id, $id]);
            $reservation = $statement->fetch();
            if (!$reservation) throw new Exception("Reservation does not exist");


            // CHECK IF RESERVATION IS ALREADY CANCELED
            if ($reservation['canceled'] == 1) throw new Exception("Reservation is already canceled");


            // CHECK ACCOMMODATION EXISTENCE
            $statement = $connection->prepare('SELECT * FROM accommodations WHERE id = ?');
            $statement->execute([$this->Accommodation_id]);
            $accommodation = $statement->fetch();
            if (!$accommodation) throw new Exception("Accommodation not found");


            // CANCEL RESERVATION
            $statement = $connection->prepare("UPDATE reservations SET canceled = 1 WHERE id = ?");
            $status = $statement->execute([$id]);
            if (!$status) throw new Exception("Failed to cancel reservation");



            // FETCH THE TRAVELER DATA
            $traveler = $_SESSION['AuthUser'];
            $host = $connection->prepare('SELECT u.* FROM users WHERE id = ?');
            $host->execute([$accommodation['host_id']]);
            $host = $host->fetch();


            $connection->commit();


            // NOTIFY HOST AND TRAVELER
            self::notifiedHost($traveler, $host, $accommodation);
            self::notifiedTraveler($traveler);

            // END TRANSACTION
        } catch (Exception $e) {

            $connection->rollBack();
            $_SESSION['error'][] = "Error canceling reservation: " . $e->getMessage();
        }
    }

    private static function notifiedHost(array $traveler, array $host, array $accommodation, ?array $reservation = null): void
    {
        // NOTIFY HOST ABOUT THE CANCELLATION
        if (!$reservation) {
            $mail = new Mail(
                to: $host['name'],
                email: $host['email'],
                subject: 'Reservation canceled',
                body: $traveler['name']  . ' whith email : ' . $traveler['email'] . ' cancel his reservation for ' . $accommodation['name'],
            );
            $mail->send();
            return;
        }
        
        // NOTIFY HOST ABOUT THE RESERVATION
        $mail = new Mail(
            to: $host['name'],
            email: $host['email'],
            subject: 'Reservation created: ' . $reservation['id'],
            body: $traveler['name'] . " with email : '" . $traveler['email'] . "'<strong> had maked a reservation </strong> for :" . $accommodation["name"] . " from : " . $reservation['start_dates'] . " to : " . $reservation['end_dates'],
        );
        $mail->send();
    }

    private static function notifiedTraveler(array $traveler, ?array $host = null, ?array $accommodation = null, ?array $reservation = null): void
    {
        // NOTIFY TRAVELER ABOUT THE CANCELLATION
        if (!$host && !$accommodation && !$reservation) {
            $mail = new Mail(
                to: $traveler['name'],
                email: $traveler['email'],
                subject: 'Reservation canceled',
                body: 'Your reservation has been canceled.',
            );
            $mail->send();
            return;
        }

        // NOTIFY TRAVELER ABOUT THE RESERVATION
        $mail = new Mail(
            to: $traveler['name'],
            email: $traveler['email'],

            subject: "confirm reservation",
            body: 'Your reservation has been confirmed.<br> Host details: <ul><li>' . $host['name'] . '</li><li> Email: ' . $host['email'] . '</li><li> Accommodation details: ' . $accommodation['name'] . '</li><li> Reservation dates: ' . $reservation['start_dates'] . ' to ' . $reservation['end_dates'] . '</li></ul>',
        );
        $mail->send();
    }

    public function downloadReceipt(): void
    {
        // code to download receipt
    }

    // show total bookings that a traveler has made
    public static function showTotalBookingsForTraveler(int $traveler_id): int
    {
        try {
            $connection = Connection::get_instance()->get_PDO();
            $statement = $connection->prepare("SELECT count(id) FROM reservations WHERE traveler_id = ?");
            $statement->execute([$traveler_id]);
            return $statement->fetchColumn();
        } catch (Exception $e) {
            $_SESSION['error'][] = "Error fetching reservations: " . $e->getMessage();
            return 0;
        }
    }

    // show total bookings that an accommodation has made
    public static function showTotalBookingsForAccommodation(int $accommodation_id): int
    {
        try {
            $connection = Connection::get_instance()->get_PDO();
            $statement = $connection->prepare("SELECT count(id) FROM reservations WHERE accommodation_id = ?");
            $statement->execute([$accommodation_id]);
            return $statement->fetchColumn();
        } catch (Exception $e) {
            $_SESSION['error'][] = "Error fetching reservations: " . $e->getMessage();
            return 0;
        }
    }

    // show total revenue generated from a specific accommodation
    public function showRevenue(): float
    {
        try {
            $connection = Connection::get_instance()->get_PDO();
            $statement = $connection->prepare("SELECT SUM(price) FROM reservations WHERE accommodation_id = ?");
            $statement->execute([$this->Accommodation_id]);
            return (float) $statement->fetchColumn();
        } catch (Exception $e) {
            $_SESSION['error'][] = "Error fetching revenue: " . $e->getMessage();
            return 0.0;
        }
    }

    public static function validateDate(DateTime $date_start, DateTime $date_end): bool
    {
        // check if the dates are valid
        $connection = Connection::get_instance()->get_PDO();
        $statement = $connection->prepare("SELECT count(id) FROM reservations WHERE start_date < ? AND end_date > ? AND canceled = 0");
        $statement->execute([$date_end, $date_start]);
        return $statement->fetchColumn() === 0;
    }
}


//As a user, I can see the details of a property (price, city, available dates, host)
//available date should be available from and available to , create a function that will calculate the available dates and update Accommodations class + reservation class

// add notification methods and email validation otp . 
