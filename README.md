# 🪪 ID Card Issuance System (ระบบจัดการบัตรประจำตัวเจ้าหน้าที่รัฐอัจฉริยะ)

![Version](https://img.shields.io/badge/version-2.0.0-blue?style=for-the-badge)
![Status](https://img.shields.io/badge/status-production-success?style=for-the-badge)
![Security](https://img.shields.io/badge/security-audited-brightgreen?style=for-the-badge)
![License](https://img.shields.io/badge/license-Private-red?style=for-the-badge)

<p align="center">
  <img src="https://nont-rtp.com/upload/api/uploads/kobkob772/kobkob772-20260306-5379b3.png" alt="IDCard System Preview" width="100%" style="border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
</p>
<p align="center">
  <img src="https://nont-rtp.com/upload/api/uploads/kobkob772/kobkob772-20260306-93edb5.png" alt="IDCard System Preview" width="100%" style="border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
</p>
> **"มาตรฐานใหม่ของการออกบัตรประจำตัวข้าราชการ ด้วยความแม่นยำ ปลอดภัย และอัจฉริยะ"**
> แพลตฟอร์มบริหารจัดการคำขอเพื่อมีบัตรประจำตัวเจ้าหน้าที่ของรัฐ แบบครบวงจร (End-to-End) พลิกโฉมการทำงานตั้งแต่การยื่นคำขอออนไลน์ จนถึงการพิมพ์บัตรตัวจริง พร้อมมาตรฐาน PDPA และเทคโนโลยีการประมวลผลภาพขั้นสูง (Advanced Image Processing)

---

## 🚀 ฟีเจอร์ที่ก้าวล้ำ (Next-Gen Features)

### 📋 ระบบยื่นคำขอและตรวจสอบอัจฉริยะ (Smart Request & Validation)

- **🛡️ PDPA & Consent Workflow:** ระบบประกาศนโยบายความเป็นส่วนตัว (Privacy Notice) และการให้ความยินยอมก่อนเริ่มกรอกข้อมูลตามมาตรฐานกฎหมายPDPA
- **🧠 Intelligent Rule Engine:** ระบบตรวจสอบความถูกต้อง (Validation) ของคำขอแบบเรียลไทม์ บังคับเลือกเงื่อนไขและเอกสารประกอบที่ถูกต้องแม่นยำ 100% ทั้งฝั่ง Frontend และ Backend
- **🔍 Seamless Status Tracking:** ข้าราชการสามารถตรวจสอบสถานะคำขอตั้งแต่ต้นจนจบ (รอตรวจสอบ -> รออนุมัติ -> ส่งพิมพ์ -> รับบัตร) ได้ทันที

### 🎨 สตูดิโอจัดการสื่อแบบบิลต์อิน (In-Browser Media Studio)

- **✂️ AI-Assisted Photo Cropping:** เครื่องมือตัดแต่งภาพถ่ายหน้าตรงให้ได้สัดส่วนมาตรฐานของบัตร (3:4) พร้อมเครื่องมือหมุนและซูม โดยพึ่งพาระบบในเบราว์เซอร์ทั้งหมด
- **✍️ Signature Transparency Engine:** นวัตกรรมระบบประมวลผลลายเซ็น (Thresholding & Contrast Ajustment) ที่สามารถลบพื้นหลัง (Background Removal) ให้โปร่งใสอัตโนมัติ พร้อมพรีวิวแบบเรียลไทม์
- **📂 Smart Document Merging:** ระบบแนบเอกสารที่ฉลาดขึ้น บันทึกประวัติไฟล์เดิมที่เคยอัปโหลดไว้และผสานเข้ากับไฟล์ใหม่ได้อย่างไร้รอยต่อเมื่อแก้ไขคำขอ

### 👨‍💼 ศูนย์ควบคุมและบัญชาการ (Admin Command Center)

- **📊 Interactive Dashboard:** แผงควบคุมสถิติและรายการคำขอแยกตามบล็อกสถานะชัดเจน ใช้งานง่ายและตอบสนองทันที
- **📝 Cross-System Staff Notes:** ระบบบันทึกข้อความจากเจ้าหน้าที่ (Staff Notes) ฝังตัวอยู่กับบุคคล เพื่อการประสานงานภายในที่ไร้รอยต่อ ทราบประวัติและข้อควรระวังของผู้ขอแต่ละคน
- **🖨️ Precision Batch Printing:** ระบบประมวลผลภาพเพื่อจัดพิมพ์บัตร (Print Engine) ที่คำนวณสัดส่วนหน้าตึกและพิกัดอย่างแม่นยำระดับพิกเซล
- **🔐 Enterprise RBAC Security:** ควบคุมการเข้าถึงด้วยระบบสิทธิ์ระดับองค์กร เชื่อมต่อตรงกับระบบ SSO ส่วนกลาง

---

## 🛠️ เทคโนโลยีขับเคลื่อน (Core Tech Stack)

|                                                      Backend Layer                                                      |                                                            Frontend Interactive                                                             |                                                Image Processing Core                                                 |                                                 System Flow                                                 |
| :---------------------------------------------------------------------------------------------------------------------: | :-----------------------------------------------------------------------------------------------------------------------------------------: | :------------------------------------------------------------------------------------------------------------------: | :---------------------------------------------------------------------------------------------------------: |
| ![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=flat-square&logo=php&logoColor=white) <br> **PHP 8.1+ & PDO** | ![TailwindCSS](https://img.shields.io/badge/TailwindCSS-3-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white) <br> **TailwindCSS 3** | ![Cropper.js](https://img.shields.io/badge/Cropper.js-1.5-blueviolet?style=flat-square) <br> **Cropper.js & Canvas** | ![SweetAlert2](https://img.shields.io/badge/SweetAlert2-11-success?style=flat-square) <br> **Swal2 Alerts** |
|                                            MySQL 8.0 <br> Strict Validation                                             |                                                       Awesomplete <br> Responsive UI                                                        |                                        SignaturePad 4.0 <br> Threshold Engine                                        |                                        Fetch API <br> Async Uploads                                         |

---

## 📂 โครงสร้างสถาปัตยกรรมโปรเจกต์ (System Architecture)

```text
idcard/
├── index.php             # Landing Page (เข้าสู่การขอบัตร หรือ ติดตามสถานะ)
├── request.php           # แบบฟอร์มยื่นคำขออัจฉริยะ (Smart Form & Validation)
├── save_request.php      # Backend Upload & Data Merge Engine
├── track_status.php      # ศูนย์รวมตรวจสอบความคืบหน้าของคำขอ
├── admin_dashboard.php   # แผงควบคุมส่วนกลาง (Command Center)
├── admin_edit.php        # อินเทอร์เฟซเจ้าหน้าที่พร้อม In-Browser Studio
├── admin_print_card.php  # เครื่องยนต์ประมวลผลการพิมพ์บัตรพลาสติก
├── config.php            # Security & Database Configuration
├── connect.php           # PDO Database Instance Manager
├── helpers.php           # Utility Core (Logs, Sanitization, DateConv)
└── uploads/              # แหล่งเก็บไฟล์ (Secure Storage Directory)
```

---

## 👨‍💻 สถาปนิกผู้พัฒนาระบบ (Lead Architect)

- ✨ **ส.ต.ต. รัฐภูมิ คำแก้ว** (Full-Stack Developer)
- 📧 Email: ratthaphum.kh@police.go.th
- 🏢 ฝ่ายอำนวยการ ๑ ตำรวจภูธรจังหวัดปทุมธานี

<p align="center"> <sub>"ยกระดับการให้บริการภาครัฐ ด้วยเทคโนโลยีที่เข้าอกเข้าใจผู้ปฏิบัติงาน" ✒️</sub> </p>

---

## ⚠️ คู่มือการติดตั้งและสิทธิ์ใช้งาน (Deployment Guide)

1. **Environment Initialization:** คัดลอก `config.php.example` เป็น `config.php` และตั้งค่าการเชื่อมต่อฐานข้อมูลให้ถูกต้อง
2. **SSO Integration:** โปรเจกต์นี้ทำงานภายใต้ร่มเงาของ **Police Cloud SSO** โปรดตรวจสอบ `CLIENT_ID` และ `CLIENT_SECRET` ให้ตรงกับระบบส่วนกลาง
3. **Storage Configuration:** ตรวจสอบสิทธิ์การเขียนไฟล์ (Write Permission) ในโฟลเดอร์ `uploads/` และ `secure_uploads/` (`chmod 755`)
4. **Security Policy:** ระบบมีกลไก `.gitignore` เพื่อป้องกันไม่ให้คอมมิตไฟล์ `config.php` และ `uploads/` ขึ้นสู่กระดานสาธารณะโดยเด็ดขาด
