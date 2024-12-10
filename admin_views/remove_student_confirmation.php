<?php
require_once '../classes/admin.class.php';

$studentObj = new Admin;


$student = $studentObj->fetch_student_details($_GET['student-id']);

?>
<div class="modal fade bd-example-modal-lg" id="removeStudent" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Remove Student</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form action="" method="POST" id="form-remove-student">
            <div class="modal-body d-flex flex-column align-items-center justify-content-center">
                <input type="hidden" name="student_id" value="<?= $_GET['student-id'] ?>">
                <p class="fs-5 mt-2 text-black text-center">
                    Remove <?= $student['last_name'] . ', ' . $student['first_name'] . ' ' . $student['middle_name']?>?
                </p>
                <div>
                    <select name="reason" id="reason" class="text-center p-1 rounded-2 border-none">
                        <option value="">Reason</option>
                        <option value="dropped">Dropped</option>
                        <option value="graduated">Graduated</option>
                        <option value="other">Other</option>
                    </select>
                    <div class="invalid-feedback fs-5"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary brand-bg-color" name="assign">Assign</button>
            </div>
        </form>
      </div>
    </div>
  </div>