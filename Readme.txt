CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uid CHAR(36) NOT NULL UNIQUE, -- UUID
    iub_id VARCHAR(10) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    department VARCHAR(100),
    major VARCHAR(100),
    minor VARCHAR(100),
    email VARCHAR(100) NOT NULL UNIQUE,
    contact_number VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    role VARCHAR(15) NOT NULL DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE administrative_staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uid CHAR(36) NOT NULL UNIQUE, /* UUID */
    full_name VARCHAR(100) NOT NULL,
    employee_id VARCHAR(20) UNIQUE NOT NULL,
    department VARCHAR(100),
    contact_number VARCHAR(20),
    iub_email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(15) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  uid CHAR(36) NOT NULL UNIQUE,         -- UUID for global unique ID
  username VARCHAR(50) NOT NULL UNIQUE, -- e.g., '2221134'
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(100) UNIQUE,
  password VARCHAR(255) NOT NULL,        -- hashed password
  role VARCHAR(50) DEFAULT 'admin',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_login TIMESTAMP NULL
);


UPDATE administrative_staff
SET role = 'administrative_staff'
WHERE role = 'administrative_';

