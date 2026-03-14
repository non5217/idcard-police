# 🪪 ID Card Issuance System (ระบบจัดการบัตรประจำตัวเจ้าหน้าที่รัฐอัจฉริยะ)

![Version](https://img.shields.io/badge/version-2.2.0-blue?style=for-the-badge)
![Status](https://img.shields.io/badge/status-production--success?style=for-the-badge)
![Security](https://img.shields.io/badge/security-audited%20%26%20secured-brightgreen?style=for-the-badge)
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

- **🛡️ PDPA & Consent Workflow:** ระบบประกาศนโยบายความเป็นส่วนตัว (Privacy Notice) และการให้ความยินยอมก่อนเริ่มกรอกข้อมูลตามมาตรฐานกฎหมาย PDPA
- **🧠 Intelligent Rule Engine:** ระบบตรวจสอบความถูกต้อง (Validation) ของข้อมูลแบบเรียลไทม์ บังคับเลือกเงื่อนไขและเอกสารประกอบที่ถูกต้องแม่นยำ 100%
- **✍️ Hybrid Signature Engine:** นวัตกรรมระบบเซ็นชื่อที่รองรับทั้งการเซ็นบนหน้าจอคอมพิวเตอร์ หรือสแกน QR Code เพื่อใช้หน้าจอมือถือเป็นแผ่นรองเซ็น (Signature Pad Extension)
- **🎞️ Signature Persistence:** ระบบจดจำลายเซ็นอัจฉริยะ ลายเซ็นไม่หายเมื่อมีการหมุนหน้าจอ หรือปรับขนาดหน้าจอบนอุปกรณ์เคลื่อนที่
- **📂 Smart Document Merging:** ระบบแนบเอกสารที่ฉลาดขึ้น บันทึกประวัติไฟล์เดิมและผสานเข้ากับไฟล์ใหม่ได้อย่างไร้รอยต่อเมื่อแก้ไขคำขอ
- **🔍 Seamless Status Tracking:** ตรวจสอบสถานะคำขอผ่าน Timeline อัจฉริยะ (รอตรวจสอบ -> รออนุมัติ -> รอพิมพ์บัตร -> พิมพ์บัตรแล้ว/รอรับ -> รับบัตรแล้ว)

### 👨‍💼 ศูนย์ควบคุมและบัญชาการ (Admin Command Center)

- **📊 Dynamic Workflow Dashboard:** แผงควบคุมสถิติและรายการคำขอที่ปุ่มดำเนินการจะเปลี่ยนชื่อตามสถานะปัจจุบันอัตโนมัติ ช่วยลดความสับสนในการทำงาน
- **📝 Cross-System Staff Notes:** ระบบบันทึกข้อความจากเจ้าหน้าที่ (Staff Notes) ฝังตัวอยู่กับบุคคล เพื่อการประสานงานภายในที่ไร้รอยต่อ
- **🖨️ Precision Batch Printing:** ระบบประมวลผลภาพเพื่อจัดพิมพ์บัตร (Print Engine) ที่คำนวณสัดส่วนและพิกัดอย่างแม่นยำระดับพิกเซล
- **🔐 Enterprise RBAC Security:** ควบคุมการเข้าถึงด้วยระบบสิทธิ์ระดับองค์กร เชื่อมต่อตรงกับระบบ SSO ส่วนกลาง

---

## 🛠️ เทคโนโลยีขับเคลื่อน (Core Tech Stack)

|                                                      Backend Layer                                                      |                                                            Frontend Interactive                                                             |                                                Image Processing Core                                                 |                                                 System Flow                                                 |
| :---------------------------------------------------------------------------------------------------------------------: | :-----------------------------------------------------------------------------------------------------------------------------------------: | :------------------------------------------------------------------------------------------------------------------: | :---------------------------------------------------------------------------------------------------------: |
| ![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=flat-square&logo=php&logoColor=white) <br> **PHP 8.1+ & PDO** | ![TailwindCSS](https://img.shields.io/badge/TailwindCSS-3-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white) <br> **TailwindCSS 3** | ![Cropper.js](https://img.shields.io/badge/Cropper.js-1.5-blueviolet?style=flat-square) <br> **Cropper.js & Canvas** | ![SweetAlert2](https://img.shields.io/badge/SweetAlert2-11-success?style=flat-square) <br> **Swal2 Alerts** |
|                                            MySQL 8.0 <br> Strict Validation                                             |                                             QRCode.js <br> SignaturePad 4.0 <br> Responsive UX                                              |                                     Signal Transfer API <br> Thresholding Engine                                     |                                         Fetch API <br> Long Polling                                         |

---

## 📂 โครงสร้างสถาปัตยกรรมโปรเจกต์ (System Architecture)

```text
idcard/
├── index.php             # Landing Page (เข้าสู่การขอบัตร หรือ ติดตามสถานะ)
├── request.php           # แบบฟอร์มยื่นคำขอและระบบเซ็นชื่ออัจฉริยะ (Hybrid Signing)
├── mobile_sig.php        # หน้าจับภาพลายเซ็นรองรับการใช้งานผ่านมือถือ (UX Optimized)
├── api/                  # ศูนย์รวม API สำหรับรับส่งข้อมูล
│   ├── save_mobile_sig.php  # รับข้อมูลลายเซ็นสดจากหน้าจอสัมผัส
│   └── get_mobile_sig.php   # จ่ายข้อมูลลายเซ็นเข้าสู่แบบฟอร์มหลัก
├── save_request.php      # Backend Upload & Data Merge Engine
├── track_status.php      # ศูนย์รวมตรวจสอบความคืบหน้าของคำขอแบบ Timeline
├── admin_dashboard.php   # แผงควบคุมส่วนกลาง (Command Center)
├── admin_edit.php        # อินเทอร์เฟซเจ้าหน้าที่พร้อม Workflow Management
├── admin_print_card.php  # เครื่องยนต์ประมวลผลการพิมพ์บัตรพลาสติก
├── config.php            # Security & Database Configuration
├── connect.php           # PDO Database Instance Manager
├── helpers.php           # Utility Core (Logs, Sanitization, DateConv)
└── temp_signatures/      # ที่พักข้อมูลลายเซ็นชั่วคราว (Auto-cleared)
```

---

## 👨‍💻 สถาปนิกผู้พัฒนาระบบ (Lead Architect)

- ✨ **ส.ต.ต. รัฐภูมิ คำแก้ว** (Full-Stack Developer)
- 📧 Email: ratthaphum.kh@police.go.th
- 🏢 ฝ่ายอำนวยการ ๑ ตำรวจภูธรจังหวัดปทุมธานี

<p align="center"> <sub>"ยกระดับการให้บริการภาครัฐ ด้วยเทคโนโลยีที่เข้าอกเข้าใจผู้ปฏิบัติงาน" ✒️</sub> </p>

---

### 🔒 ความปลอดภัยและการแก้ไข (Security Audit & Fixes)

#### ✅ **Security Improvements Applied:**
- **🔒 Environment Variables:** แยก configuration ไว้ใน `.env` และ `env_loader.php`
- **🛡️ CSRF Protection:** เปิดใช้งาน OAuth state validation และ form tokens
- **🔒 SSL Verification:** เปิดใช้งาน SSL certificate verification ใน cURL requests
- **🔍 Input Validation:** เพิ่มการตรวจสอบและ sanitization ของข้อมูล
- **🌐 CORS Protection:** จำกัด CORS policy ให้ domain ที่ระบุ
- **📁 File Permissions:** แก้ไข upload directory permissions เป็น 0755
- **🔄 Session Management:** ปรับปรับ session cookie configuration สำหรับ cross-domain

#### 🛡️ **Vulnerabilities Fixed:**
- ❌ **Hardcoded Secrets** → ✅ **Environment Variables**
- ❌ **CSRF Vulnerability** → ✅ **State Validation**
- ❌ **SSL Verification Disabled** → ✅ **SSL Enabled**
- ❌ **Wildcard CORS Policy** → ✅ **Domain-Specific CORS**
- ❌ **Insecure File Permissions** → ✅ **Secure Directory Permissions**
- ❌ **Missing Input Validation** → ✅ **Comprehensive Validation**

#### 🔧 **Configuration Management:**
- **`.env` File:** จัดเก็บ database credentials, API keys, และ secrets
- **`env_loader.php`:** โหลด environment variables และสร้าง constants
- **`.gitignore`:** ป้องกันการ commit sensitive configuration files

---

## ⚠️ คู่มือการติดตั้งและสิทธิ์ใช้งาน (Deployment Guide)

1. **Environment Initialization:** คัดลอก `config.php.example` เป็น `config.php` และตั้งค่าการเชื่อมต่อฐานข้อมูลให้ถูกต้อง
2. **SSO Integration:** โปรเจกต์นี้ทำงานภายใต้ร่มเงาของ **Police Cloud SSO** โปรดตรวจสอบ `CLIENT_ID` และ `CLIENT_SECRET` ให้ตรงกับระบบส่วนกลาง
3. **Storage Configuration:** ตรวจสอบสิทธิ์การเขียนไฟล์ (Write Permission) ในโฟลเดอร์ `uploads/`, `secure_uploads/` และ `temp_signatures/` (`chmod 755`)
4. **Environment Configuration:** ใช้ไฟล์ `.env` สำหรับจัดเก็บ database credentials, API keys, และ secrets
5. **Security Policy:** ระบบมีไฟล์ `.env` และ `.gitignore` เพื่อป้องกันการ commit sensitive configuration files
