# 🪪 Police ID Card Issuance System

![Version](https://img.shields.io/badge/version-1.5.0-blue?style=for-the-badge)
![Status](https://img.shields.io/badge/status-stable-success?style=for-the-badge)
![License](https://img.shields.io/badge/license-Private-red?style=for-the-badge)

<p align="center">
  <img src="https://img2.pic.in.th/idcard_preview_v150_1.png" alt="IDCard System Preview" width="100%">
</p>

> **"มาตรฐานใหม่ การออกบัตรข้าราชการตำรวจ ด้วยความแม่นยำและปลอดภัย"**
> แพลตฟอร์มบริหารจัดการคำขอเพื่อมีบัตรประจำตัวเจ้าหน้าที่ของรัฐ แบบครบวงจร (End-to-End) ตั้งแต่การยื่นคำขอออนไลน์ จนถึงการพิมพ์บัตรตัวจริง พร้อมมาตรฐาน PDPA

---

## 🚀 ฟีเจอร์หลัก (Key Features)

### 📋 ระบบยื่นคำขอและติดตาม (Request & Tracking)

- **🛡️ PDPA Compliant Form:** ระบบประกาศนโยบายความเป็นส่วนตัว (Privacy Notice) และการให้ความยินยอมก่อนเริ่มกรอกข้อมูลตามมาตรฐานกฎหมาย
- **🔍 Status Tracking:** ข้าราชการสามารถตรวจสอบสถานะคำขอ (รอตรวจสอบ, รออนุมัติ, บัตรเสร็จแล้ว) ได้ทันทีผ่านเลขบัตรประชาชนและเบอร์โทรศัพท์
- **📱 Mobile-Friendly Design:** อินเทอร์เฟซรองรับการใช้งานผ่านสมาร์ตโฟนอย่างสมบูรณ์แบบ (Responsive Layout)

### 🎨 ระบบจัดการไฟล์และสื่อ (Media & Image Tools)

- **✂️ Smart Photo Cropping:** เครื่องมือตัดแต่งภาพถ่ายหน้าตรงให้ได้สัดส่วนมาตรฐาน (25:30) ภายในเว็บไซต์โดยไม่ต้องพึ่งโปรแกรมอื่น
- **✍️ Signature Master:** รองรับทั้งการเซ็นชื่อสดผ่านหน้าจอ (Signature Pad) และการอัปโหลดรูปภาพ พร้อมโหมดลบพื้นหลัง (Background Removal) ให้โปร่งใสอัตโนมัติ
- **📂 Document Management:** จัดการไฟล์แนบ (PDF/JPG) แยกตามประเภทเหตุผลการขอบัตร (ครั้งแรก, บัตรหมดอายุ, เลื่อนยศ)

### 👨‍💼 ระบบบริหารจัดการ (Admin Management)

- **📊 Multi-Status Dashboard:** รวมสถิติและรายการคำขอแยกตามบล็อกสถานะชัดเจน
- **🖨️ Batch Printing:** ระบบพิมพ์บัตร (Card Printing) พร้อมพรีวิวสัดส่วนที่แม่นยำ และการบันทึกประวัติการพิมพ์
- **🔐 RBAC Security:** ควบคุมการเข้าถึงด้วยระบบสิทธิ์ (Super Admin, Admin, Staff) และเชื่อมต่อ SSO ของหน่วยงาน

---

## 🛠️ เทคโนโลยีที่ใช้ (Tech Stack)

|                                          Backend Layer                                          |                                                    Frontend Layer                                                    |                                    Image Processing                                     |                                        Components                                        |
| :---------------------------------------------------------------------------------------------: | :------------------------------------------------------------------------------------------------------------------: | :-------------------------------------------------------------------------------------: | :--------------------------------------------------------------------------------------: |
| ![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=flat-square&logo=php&logoColor=white) | ![TailwindCSS](https://img.shields.io/badge/TailwindCSS-3-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white) | ![Cropper.js](https://img.shields.io/badge/Cropper.js-1.5-blueviolet?style=flat-square) | ![SignaturePad](https://img.shields.io/badge/SignaturePad-4.0-success?style=flat-square) |
|                                         **PDO / MySQL**                                         |                                                  **Swal2 / JQL.th**                                                  |                                     **Canvas API**                                      |                                     **Awesomplete**                                      |

---

## 📂 โครงสร้างโปรเจกต์ (Project Structure)

```text
idcard/
├── index.php           # หน้าหลัก (เลือกระหว่าง ขอมีบัตร หรือ ติดตามสถานะ)
├── request.php         # แบบฟอร์มยื่นคำขออัจฉริยะ (Photo/Signature/PDPA)
├── track_status.php    # หน้าตรวจสอบความคืบหน้าของคำขอ
├── admin_dashboard.php # ส่วนควบคุมกลางสำหรับเจ้าหน้าที่
├── admin_print_card.php# ระบบจัดพิมพ์บัตร (Print Engine)
├── admin_edit.php      # ส่วนตรวจสอบความถูกต้องของข้อมูลและเอกสาร
├── config.php          # การตั้งค่า (DB, SSO, API) **[Local Only]**
├── connect.php         # Database Instance (PDO)
├── helpers.php         # ฟังก์ชันเสริม (Logs, Sanitization, DateConv)
└── uploads/            # โฟลเดอร์เก็บไฟล์สื่อและเอกสาร (Secure Path)
```

---

## 👨‍💻 ผู้พัฒนา (Author)

- ✨ **ส.ต.ต. รัฐภูมิ คำแก้ว** (Developer)
- 📧 Email: ratthaphum.kh@police.go.th
- 🏢 ฝ่ายอำนวยการ ๑ ตำรวจภูธรจังหวัดปทุมธานี

---

## ⚠️ การติดตั้งและขอสิทธิ์ (Installation)

1. **Environment:** คัดลอก `config.php.example` เป็น `config.php` และตั้งค่าการเชื่อมต่อฐานข้อมูล
2. **SSO:** โปรเจกต์นี้ใช้การยืนยันตัวตนผ่าน Police Cloud SSO โปรดลงทะเบียน `CLIENT_ID` และ `CLIENT_SECRET` ก่อนใช้งาน
3. **Storage:** ตรวจสอบสิทธิ์การเขียนไฟล์ (Write Permission) ในโฟลเดอร์ `uploads/`
4. **.gitignore:** ระบบห้ามนำไฟล์ `config.php` ขึ้น GitHub เป็นอันขาด
