<?php
    require_once 'database.class.php';

    class Signup {
        private $conn;
        
        function __construct (){
            $db = new Database;
            $this->conn = $db->connect();
        }

        // Login function with check for facilitator
        function login($email, $password) {
            $sql = "SELECT user_id, password, is_facilitator, is_student, is_admin FROM user WHERE email = :email LIMIT 1;";
            $query = $this->conn->prepare($sql);
            $query->bindParam(':email', $email);
            $query->execute();
            
            $user = $query->fetch();
    
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user'] = [
                    'user_id' => $user['user_id'],
                    'is_facilitator' => $user['is_facilitator'],
                    'is_student' => $user['is_student'],
                    'is_admin' => $user['is_admin']
                ];
                $date = date('Y-m-d H:i:s');
                $sql_update = "UPDATE user SET date_updated = :date_updated WHERE user_id = :user_id;";
                $query_update = $this->conn->prepare($sql_update);
                $query_update->bindParam(':user_id', $_SESSION['user']['user_id']);
                $query_update->bindParam(':date_updated', $date);
                $query_update->execute();
                
                header('location: user/dashboard.php');
            } else {
                $_SESSION['incorrect_credentials'] = 'Incorrect Credentials';
            }
        }

        // New function to check if the user is a facilitator (helper for switch role modal)
        // function isFacilitator($user_id) {
        //     $sql = "SELECT is_facilitator FROM user WHERE user_id = :user_id;";
        //     $query = $this->conn->prepare($sql);
        //     $query->bindParam(':user_id', $user_id);
        //     $query->execute();

        //     $user = $query->fetch();
        //     return $user['is_facilitator'] == 1;
        // }

        // function create_admin($email, $password){
        //     $sql = "INSERT INTO user(email, password, date_created, is_student) 
        //             VALUES(:email, :password, NOW(), 0);";
        //     $query = $this->conn->prepare($sql);
        //     $query->bindParam(":email", $email);
        //     $query->bindParam(":password", $password);
        //     $query->execute();
        //     return true;
        // }



        function sign_up_and_set_profile($email, $password, $course_id, $course_year, $last_name, $first_name, $middle_name, $phone_number, $dob, $age, $course_section) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Check if email already exists
            if ($this->duplicate_record_exists('user', ['email' => $email])) {
                return ['error' => 'Email already exists'];
            }
        
            // Insert into 'user' table
            $sql = "INSERT INTO user(email, password, date_created) VALUES (:email, :password, NOW());";
            $query = $this->conn->prepare($sql);
            $query->bindParam(":email", $email);
            $query->bindParam(":password", $hashed_password);
        
            if ($query->execute()) {
                $user_id = $this->conn->lastInsertId();
                $_SESSION['user_id'] = $user_id;
        
                if ($this->duplicate_record_exists('student', ['student_id' => $user_id])) {
                    return ['error' => 'Duplicate profile exists'];
                } 
        
                // Set profile
                $this->set_profile($user_id, $course_id, $course_year, $last_name, $first_name, $middle_name, $phone_number, $dob, $age, $course_section);
                return ['success' => true];
            }
        
            return ['error' => 'Failed to create user.'];
        }
        
        
        function set_profile($user_id, $course_id, $course_year, $last_name, $first_name, $middle_name, $phone_number, $dob, $age, $course_section) {
            // Insert profile into 'student' table
            $sql_stud = "INSERT INTO student(student_id, course_id, last_name, first_name, middle_name, phone_number, dob, age, course_year, course_section)
                         VALUES (:user_id, :course_id, :last_name, :first_name, :middle_name, :phone_number, :dob, :age, :course_year, :course_section);";
            $query_stud = $this->conn->prepare($sql_stud);
        
            // Bind parameters and execute query
            $query_stud->bindParam(":user_id", $user_id);
            $query_stud->bindParam(":course_id", $course_id);
            $query_stud->bindParam(":last_name", $last_name);
            $query_stud->bindParam(":first_name", $first_name);
            $query_stud->bindParam(":middle_name", $middle_name);
            $query_stud->bindParam(":phone_number", $phone_number);
            $query_stud->bindParam(":dob", $dob);
            $query_stud->bindParam(":age", $age);
            $query_stud->bindParam(":course_year", $course_year);
            $query_stud->bindParam(":course_section", $course_section);
        
            $query_stud->execute();
        
            // Update the 'user' table with the current date
            $sql_date_updated = "UPDATE user SET date_updated = :date_updated WHERE user_id = :user_id";
            $query_date_updated = $this->conn->prepare($sql_date_updated);
        
            $current_date = date('Y-m-d H:i:s');
        
            $query_date_updated->bindParam(':date_updated', $current_date);
            $query_date_updated->bindParam(':user_id', $user_id);
        
            $query_date_updated->execute();
        
            // Insert user into student_organization
            $collection_fee = $this->get_collection_fee();
            
            foreach($collection_fee as $org){
                $sql_pending_balance = "UPDATE collection_fees SET pending_balance = pending_balance + :amount
                WHERE collection_id = :collection_id AND label = 'required'";
                $query_pending_balance = $this->conn->prepare($sql_pending_balance);
    
                $query_pending_balance->bindParam(":collection_id", $org['collection_id']);
                $query_pending_balance->bindParam(":amount", $org['amount']);
    
                $query_pending_balance->execute();

                $sql_stud_org = "INSERT INTO payment(student_id, collection_id, semester, amount_to_pay) VALUES(:student_id, :collection_id, 'First Semester', :amount_to_pay);";
                $query_stud_org = $this->conn->prepare($sql_stud_org);
                
                $query_stud_org->bindParam(':student_id', $user_id);
                $query_stud_org->bindParam(':collection_id', $org['collection_id']);
                $query_stud_org->bindParam(':amount_to_pay', $org['amount']);
        
                $query_stud_org->execute();
            }

            return true;
        }


        function get_collection_fee() {
            $sql = "SELECT collection_id, amount FROM collection_fees WHERE request_status = 'Approved';";
            $query = $this->conn->prepare($sql);

            if($query->execute()){
                return $query->fetchAll();
            } else {
                return false;
            }
        }

        // function update_user_type($is_student, $is_facilitator, $user_id){
        //     $sql_type = "UPDATE user SET is_student = :is_student, is_facilitator = :is_facilitator WHERE user_id = :user_id;";
        //     $query_type = $this->conn->prepare($sql_type);

        //     $query_type->bindParam(":is_student",$is_student);
        //     $query_type->bindParam(":is_facilitator",$is_facilitator);
        //     $query_type->bindParam(":user_id",$user_id);

        //     $query_type->execute();
        // }


        function duplicate_record_exists($table, $data) {
            $sql = "SELECT COUNT(*) FROM $table WHERE ";
            $conditions = [];
            foreach ($data as $column => $value) {
                $conditions[] = "$column = :$column";
            }
            $sql .= implode(' AND ', $conditions) . " LIMIT 1;";
            $query = $this->conn->prepare($sql);
            foreach ($data as $column => $value) {
                $query->bindParam(":$column", $value);
            }
            $query->execute();
            $count = $query->fetchColumn();
            return $count > 0;
        }

        function getCourse(){
            $sql = "SELECT * FROM course;";
            $query = $this->conn->prepare($sql);

            if($query->execute()){
                return $query->fetchAll();
            } else {
                return false;
            }
        }


        // function getUser($user_id) {
        //     $sql = 'SELECT user_id, user_type FROM user WHERE user_id = :user_id';
        //     $query = $this->conn->prepare($sql);

        //     $query->bindParam(':user_id', $user_id);

        //     if($query->execute()){
        //         return $query->fetch();
        //     } else {
        //         return false;
        //     }
        // }
    }

    // $signupObj = new Signup;
    // var_dump($signupObj->getOrganization());
?>

