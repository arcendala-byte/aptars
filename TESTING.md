# APTARS — System Testing Checklist

## 1. Installation
- [ ] `aptars_db` imported with all tables
- [ ] `http://localhost/aptars/` loads the landing page
- [ ] Login page accessible

## 2. Authentication
- [ ] Admin / lecturer / student log in to correct dashboards
- [ ] Wrong password rejected
- [ ] Logout works
- [ ] Role-based redirects work

## 3. Academic Structure (Admin)
- [ ] Departments CRUD
- [ ] Programmes CRUD
- [ ] Subjects CRUD
- [ ] Academic Years CRUD
- [ ] Semesters CRUD
- [ ] Lecturers CRUD
- [ ] Students CRUD

## 4. Allocations & Registration
- [ ] Assign lecturer to subject/semester
- [ ] Register student for subjects

## 5. Assessments & Results
- [ ] Create assessments
- [ ] Enter marks (admin)
- [ ] Enter marks (lecturer, own subjects)
- [ ] Grades auto-compute

## 6. Attendance
- [ ] Record attendance sessions
- [ ] Percentage calculates correctly

## 7. Influencing Factors
- [ ] Define factors
- [ ] Rate students

## 8. Analysis
- [ ] Performance analysis page loads real numbers
- [ ] Trend and grade charts render
- [ ] Factor analysis scatter plots
- [ ] Attention alerts detect flagged students

## 9. Reports
- [ ] Student report prints cleanly
- [ ] Subject report
- [ ] Class report
- [ ] Performance report (admin only)
- [ ] Access control enforced per role

## 10. Security
- [ ] CSRF protection on POST forms
- [ ] SQL injection attempts fail safely
- [ ] XSS input escaped
- [ ] Passwords hashed with bcrypt

## 11. Final
- [ ] No PHP warnings or errors
- [ ] `APP_DEBUG` set to false for handover
- [ ] Database backed up