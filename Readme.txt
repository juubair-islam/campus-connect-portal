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

UPDATE administrative_staff
SET role = 'administrative_staff'
WHERE role = 'administrative_';

CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uid CHAR(36) NOT NULL UNIQUE,         -- UUID for secure, unique identifier
    username VARCHAR(50) NOT NULL UNIQUE, -- Login ID (e.g., 2221134)
    full_name VARCHAR(100) NOT NULL,      -- Admin's full name
    email VARCHAR(100) NOT NULL UNIQUE,   -- Contact email
    password VARCHAR(255) NOT NULL,       -- Hashed password
    role ENUM('admin') DEFAULT 'admin',   -- Role type
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE courses (
    course_id INT AUTO_INCREMENT PRIMARY KEY,
    tutor_uid CHAR(36) NOT NULL,
    course_code VARCHAR(20) NOT NULL UNIQUE,
    course_name VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    credits INT NOT NULL,
    description TEXT NOT NULL,
    available_days VARCHAR(50) NOT NULL,    -- e.g. "Mon,Wed,Fri"
    start_time TIME NOT NULL,                -- e.g. '07:00:00'
    end_time TIME NOT NULL,                  -- e.g. '19:00:00'
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tutor_uid) REFERENCES students(uid) ON DELETE CASCADE
);

CREATE TABLE posts (
    post_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    post_title VARCHAR(255) NOT NULL,
    post_content TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE
);

CREATE TABLE course_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    learner_uid CHAR(36) NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (learner_uid) REFERENCES students(uid) ON DELETE CASCADE,
    UNIQUE (course_id, learner_uid)
);

CREATE TABLE course_enrollments (
    enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    learner_uid CHAR(36) NOT NULL,
    enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (learner_uid) REFERENCES students(uid) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (course_id, learner_uid)
);

CREATE TABLE course_materials (
    material_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_url VARCHAR(500),
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE
);

CREATE TABLE messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    sender_uid CHAR(36) NOT NULL,
    receiver_uid CHAR(36) NOT NULL,
    message_text TEXT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_status BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (sender_uid) REFERENCES students(uid) ON DELETE CASCADE,
    FOREIGN KEY (receiver_uid) REFERENCES students(uid) ON DELETE CASCADE
);

ALTER TABLE courses
DROP COLUMN subject,
DROP COLUMN credits;

-- Lost items table
CREATE TABLE lost_items (
    lost_id INT AUTO_INCREMENT PRIMARY KEY,
    reporter_uid CHAR(36) NOT NULL,             -- Who reported the lost item
    item_name VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(100),                      -- e.g. "Electronics", "Books", "Clothing"
    lost_date DATE,
    location VARCHAR(255),
    contact_number VARCHAR(20),
    image LONGBLOB,                             -- Store image file directly
    image_type VARCHAR(50),                     -- MIME type: 'image/jpeg', 'image/png'
    status ENUM('open', 'found', 'closed') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reporter_uid) REFERENCES students(uid) ON DELETE CASCADE
);


-- Found items table
CREATE TABLE found_items (
    found_id INT AUTO_INCREMENT PRIMARY KEY,
    finder_uid CHAR(36) NOT NULL,               -- Who reported the found item
    item_name VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    found_date DATE,
    location VARCHAR(255),
    image LONGBLOB,                             -- Store image file directly
    image_type VARCHAR(50),                     -- MIME type
    status ENUM('unclaimed', 'claimed') DEFAULT 'unclaimed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (finder_uid) REFERENCES students(uid) ON DELETE CASCADE
);


-- Collection records (when owner claims a found item)
CREATE TABLE found_item_collections (
    collection_id INT AUTO_INCREMENT PRIMARY KEY,
    found_id INT NOT NULL,                      -- Link to found item
    owner_name VARCHAR(100) NOT NULL,
    owner_iub_id VARCHAR(20) NOT NULL,
    owner_contact VARCHAR(20) NOT NULL,
    collected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (found_id) REFERENCES found_items(found_id) ON DELETE CASCADE
);
