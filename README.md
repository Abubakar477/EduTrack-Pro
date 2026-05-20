# 🎓 EduTrack Pro — Student Performance Dashboard

**EduTrack Pro** is a modern, responsive, and feature-rich student dashboard built to help educational institutions track academic performance, student grades, GPA progression, and monthly attendance. 

Featuring a premium **Dark-Mode Glassmorphic User Interface**, it provides clear data visualization for both students and teachers.

---

## ✨ Key Features

### 🧑‍🎓 Student Portal
* **Performance Charts**: Real-time bar charts showing marks per subject and line charts showing historical GPA trends across semesters.
* **Attendance Calendar**: A visual, interactive grid calendar showing monthly attendance status (Present, Absent, Late, or Holiday).
* **GPA Speedometer**: Real-time display of current semester GPA and Cumulative GPA (CGPA).
* **PDF Export**: Generate and download professional, formatted progress report PDFs with a single click.

### 🧑‍🏫 Teacher Portal
* **Student Directory**: A centralized panel to search, view, and manage student accounts.
* **Attendance Tracker**: Log daily attendance with custom statuses (Present, Absent, Late) for all enrolled students.
* **Grades Management**: Upload, modify, and manage subject marks and credit hours for student courses.

---

## 🛠️ Technology Stack
* **Frontend**: HTML5, Vanilla CSS3 (Custom HSL color system, Glassmorphism, backdrop filters, CSS grids, and smooth animations), JavaScript.
* **Charts**: [Chart.js](https://www.chartjs.org/) for beautiful dynamic performance metrics.
* **Backend**: PHP (Structured PHP with active session management & role-based route authentication).
* **Database**: MySQL (relational structure with automated foreign key constraints and cascade deletes).
* **PDF Engine**: [FPDF](http://www.fpdf.org/) for programmatic report compiling.

---

## 🚀 Quick Setup & Installation

### 1. Prerequisites
Ensure you have **XAMPP** (or any local server stack containing Apache and MySQL) installed on your system.

### 2. Project Location
Clone or move this project repository into your local web root folder:
```text
C:\xampp\htdocs\student-dashboard\
```

### 3. Initialize Database & Assets
We've built a one-click setup script to initialize the project for you:
1. Open the **XAMPP Control Panel** and start both **Apache** and **MySQL**.
2. Open your web browser and navigate to:
   [http://localhost/student-dashboard/setup.php](http://localhost/student-dashboard/setup.php)
3. Click the **Run Setup** button. This script will automatically:
   * Create the `student_dashboard` database.
   * Import all relational tables and constraints from `database/schema.sql`.
   * Seed the database with complete demo records (students, teachers, grades, and attendance).
   * Fetch and extract the required `FPDF` library and default fonts.

### 4. Run the Dashboard
Navigate to the login screen:
[http://localhost/student-dashboard/auth/login.php](http://localhost/student-dashboard/auth/login.php)

---

## 🔑 Demo Login Credentials

For testing and demonstration purposes, the following pre-configured user credentials are created during setup:

### 🧑‍🏫 Teacher Portal
* **Email**: `teacher@school.com`
* **Password**: `teacher123`

### 🧑‍🎓 Student Portal
* **Email**: `ali@school.com` (or `sara@school.com`)
* **Password**: `student123`

---

## 📂 Project Structure
```text
student-dashboard/
├── assets/
│   ├── css/
│   │   └── style.css       # Premium Dark-mode / Glassmorphic styles
│   └── js/
│       └── charts.js      # Chart.js helper functions & theme config
├── auth/
│   ├── login.php          # Sign-in portal
│   └── logout.php         # Destroy sessions & exit
├── config/
│   └── db.php             # Database connection & shared functions
├── database/
│   └── schema.sql         # SQL structural blueprint & seed data
├── fpdf/                  # Auto-generated PDF library files
├── includes/
│   ├── auth_check.php     # Middleware for user roles
│   ├── header.php         # Shared responsive sidebar & navbar navigation
│   └── footer.php         # Shared footer scripts & charts loading
├── pdf/
│   └── report.php         # PDF Report generator
├── student/
│   ├── dashboard.php      # Main student dashboard
│   ├── attendance.php     # Month-by-month attendance tracker
│   ├── marks.php          # Complete graded coursework
│   └── gpa.php            # Credit hours and semester GPA log
├── teacher/
│   ├── dashboard.php      # Main teacher dashboard
│   ├── manage_students.php# Search and edit students
│   ├── upload_grades.php  # Submit marks & courses
│   └── attendance.php     # Submit daily attendance sheets
└── setup.php              # Automated dependency & database installer
```
