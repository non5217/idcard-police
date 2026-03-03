<?php
// idcard/helpers.php

// 1. แปลงเลขอารบิก เป็น เลขไทย
function toThaiNum($number)
{
    $thai_digits = ['๐', '๑', '๒', '๓', '๔', '๕', '๖', '๗', '๘', '๙'];
    $arabic_digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    return str_replace($arabic_digits, $thai_digits, $number);
}

// 2. แปลงวันที่เป็นรูปแบบไทย (เช่น ๑๔ กุมภาพันธ์ ๒๕๖๗)
function thaiDate($dateString, $short = false)
{
    if (!$dateString)
        return "-";
    $months = [
        1 => "มกราคม", 2 => "กุมภาพันธ์", 3 => "มีนาคม", 4 => "เมษายน", 5 => "พฤษภาคม", 6 => "มิถุนายน",
        7 => "กรกฎาคม", 8 => "สิงหาคม", 9 => "กันยายน", 10 => "ตุลาคม", 11 => "พฤศจิกายน", 12 => "ธันวาคม"
    ];
    $monthsShort = [
        1 => "ม.ค.", 2 => "ก.พ.", 3 => "มี.ค.", 4 => "เม.ย.", 5 => "พ.ค.", 6 => "มิ.ย.",
        7 => "ก.ค.", 8 => "ส.ค.", 9 => "ก.ย.", 10 => "ต.ค.", 11 => "พ.ย.", 12 => "ธ.ค."
    ];

    $timestamp = strtotime($dateString);
    $d = date('j', $timestamp);
    $m = (int)date('n', $timestamp);
    $y = date('Y', $timestamp) + 543;

    $mText = $short ? $monthsShort[$m] : $months[$m];
    return toThaiNum("$d $mText $y");
}


// 4. คำนวณอายุ (ณ วันปัจจุบัน)
function calculateAge($birthDate)
{
    $birth = new DateTime($birthDate);
    $today = new DateTime();
    $diff = $today->diff($birth);
    return $diff->y;
}
// เพิ่มฟังก์ชันนี้ลงในไฟล์ helpers.php
function calculateCardExpiry($birth_date, $issue_date, $is_pension = false)
{
    if (empty($birth_date) || empty($issue_date))
        return null;

    $dob = new DateTime($birth_date);
    $issue = new DateTime($issue_date);
    $age = $issue->diff($dob)->y;

    // 1. คำนวณวันหมดอายุปกติ (6 ปี นับแต่วันออกบัตร - ชนวันก่อนหน้า)
    $normal_expire = clone $issue;
    $normal_expire->modify('+6 years')->modify('-1 day');

    // 2. แยกโลจิกตามประเภทบัตรข้าราชการ/บำนาญ
    if ($is_pension) {
        if ($age >= 64) {
            return '9999-12-31'; // ค่าแทนคำว่า ตลอดชีพ
        }
        else {
            return $normal_expire->format('Y-m-d');
        }
    }
    else {
        // บัตรปกติ
        $retire_year = (int)$dob->format('Y') + 60;

        // เกิด 2 ต.ค. เป็นต้นไป -> เกษียณในปีถัดไป (30 ก.ย. ปีหน้า)
        if ($dob->format('m-d') > '10-01') {
            $retire_year += 1;
        }

        $retire_date = new DateTime($retire_year . '-09-30');

        // ช่วงอายุ 54 - 60 ปีเกษียณ -> ใช้ได้ถึงวันเกษียณ
        if ($age >= 54) {
            return $retire_date->format('Y-m-d');
        }
        else {
            return $normal_expire->format('Y-m-d');
        }
    }
}
?>