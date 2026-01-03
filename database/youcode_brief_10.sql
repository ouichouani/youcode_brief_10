-- - user (id , name , email , password , role , img , active, created_at , updated_at ) .
-- - Accommodations (id , name , price , city , img, active , available dates ,#host ,  created_at , updated_at) .
-- - Reservations (id , canceled , #Accommodation_id , #traveler ,  created_at , updated_at ) .
-- - Favorites (id , #user_id , #Accommodation_id ,  created_at , updated_at) .
-- - Reviews (id , rate ,#user_id , #Accommodation_id ,  created_at , updated_at)

-- SHOW DATABASES;
CREATE DATABASE youcode_brief_10;

USE youcode_brief_10;

CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'traveler', 'host') NOT NULL DEFAULT 'traveler' ,
    img VARCHAR(255) DEFAULT NULL ,
    active BOOLEAN DEFAULT 1 ,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE Accommodations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    price FLOAT NOT NULL,
    city INT NOT NULL,
    img VARCHAR(255) DEFAULT NULL,
    active BOOLEAN NOT NULL DEFAULT 1,
    available_dates DATETIME DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    host_id INT NULL,
    CONSTRAINT fk_host_Accommodation FOREIGN KEY (host_id) REFERENCES users (id) ON DELETE CASCADE
);

create table Reservations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    canceled BOOLEAN NOT NULL DEFAULT 0 ,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    traveler_id INT NULL,
    CONSTRAINT fk_traveler_reservation FOREIGN KEY (traveler_id) REFERENCES users (id) ON DELETE CASCADE,
    Accommodation_id INT NULL,
    CONSTRAINT fk_Accommodation_reservation FOREIGN KEY (Accommodation_id) REFERENCES Accommodations (id) ON DELETE CASCADE
);

create table Favorites (
    id INT PRIMARY KEY AUTO_INCREMENT,

    Accommodation_id INT NULL,
    CONSTRAINT fk_Accommodation_favorite FOREIGN KEY (Accommodation_id) REFERENCES Accommodations (id) ON DELETE CASCADE ,
    user_id INT NULL,
    CONSTRAINT fk_user_favorite FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);


create table Reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    rate SMALLINT CHECK (rate BETWEEN 0 AND 5) NOT NULL , 
    Accommodation_id INT NULL,
    CONSTRAINT fk_Accommodation_review FOREIGN KEY (Accommodation_id) REFERENCES Accommodations(id) ON DELETE CASCADE ,
    user_id INT NULL,
    CONSTRAINT fk_user_review FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);


use youcode_brief_10 ;
select * from users ;