-- 5.2.1 Login Database Table
CREATE TABLE Login (
    id INT PRIMARY KEY,
    email VARCHAR(50),
    password VARCHAR(50),
    status INT,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    gender ENUM('Male', 'Female', 'Other'),
    occupation VARCHAR(50),
    contact INT
);

-- 5.2.2 Budgets Database Table
CREATE TABLE Budgets (
    budget_id INT PRIMARY KEY,
    User_id INT,
    category VARCHAR(50),
    amount DECIMAL(10, 2),
    period ENUM('monthly', 'weekly'),
    FOREIGN KEY (User_id) REFERENCES Login(id)
);

-- 5.2.3 Goal Database Table
CREATE TABLE Goal (
    Goal_id INT PRIMARY KEY,
    User_id INT,
    title VARCHAR(50),
    Target_amount DECIMAL(10, 2),
    Current_amount DECIMAL(10, 2),
    Start_date DATE,
    End_date DATE,
    FOREIGN KEY (User_id) REFERENCES Login(id)
);

-- 5.2.4 Transaction Database Table
CREATE TABLE Transaction (
    Transaction_id INT PRIMARY KEY,
    User_id INT,
    amount DECIMAL(10, 2),
    category VARCHAR(50),
    type ENUM('income', 'expense'),
    Trans_date DATE,
    description TEXT,
    Goal_id INT,
    FOREIGN KEY (User_id) REFERENCES Login(id),
    FOREIGN KEY (Goal_id) REFERENCES Goal(Goal_id)
);
