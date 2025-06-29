# SJA Foundation - Investment Management Platform

A comprehensive web-based investment management platform with MLM referral system, secure wallet management, KYC verification, and modern glassmorphism UI.

## 🚀 Features

### Core Features
- **Modern Glassmorphism UI** - Beautiful, responsive design with dark/light mode
- **Role-based Authentication** - Admin and Client panels with secure session management
- **Investment Management** - Multiple investment plans with 11-month maturity
- **MLM Referral System** - 11-level commission structure with attractive earning opportunities
- **Secure Wallet System** - Multi-layer encryption and secure transaction processing
- **KYC Verification** - Complete document upload and video KYC support
- **Real-time Notifications** - System alerts, birthday reminders, and status updates
- **Progressive Web App** - Offline functionality and mobile app-like experience

### Investment Plans
1. **Basic Plan** - ₹10,000 to ₹1,00,000
2. **Premium Plan** - ₹1,00,000 to ₹10,00,000 
3. **Elite Plan** - ₹10,00,000+

### MLM Commission Levels
1. Professional Ambassador (₹1L–20L) - 0.25%
2. Rubies Ambassador (₹30L) - 0.37%
3. Topaz Ambassador (₹40L) - 0.50%
4. Silver Ambassador (₹50L) - 0.70%
5. Golden Ambassador (₹60L) - 0.85%
6. Platinum Ambassador (₹70L) - 1.00%
7. Diamond Ambassador (₹80L) - 1.25%
8. MTA (₹90L) - 1.50%
9. Channel Partner (₹1CR) - 2.00%
10. Co-Director (₹1.5CR) - 2.50%
11. Director/MD/CEO/CMD (₹2CR+) - 3.00%

## 🛠️ Technology Stack

- **Frontend**: HTML5, JavaScript (Vanilla), Tailwind CSS
- **Backend**: PHP 8.0+
- **Database**: MySQL 8.0+
- **Libraries**: Chart.js, jsPDF, WebRTC
- **PWA**: Service Worker, Web App Manifest

## 📦 Installation

### Prerequisites
- Web server (Apache/Nginx)
- PHP 8.0 or higher
- MySQL 8.0 or higher
- mod_rewrite enabled (for Apache)

### Quick Setup

1. **Clone or download the project**
   ```bash
   git clone <repository-url>
   cd sja-foundation
   ```

2. **Set up web server**
   - Place files in your web server's document root
   - For XAMPP: Copy to `htdocs/sja-foundation/`
   - For Apache: Copy to `/var/www/html/sja-foundation/`

3. **Configure database**
   - Create a MySQL database named `sja_foundation`
   - Ensure MySQL user has full privileges

4. **Run the setup wizard**
   - Open your browser and navigate to: `http://localhost/sja-foundation/setup.html`
   - Follow the 3-step installation wizard:
     - **Step 1**: Configure database connection
     - **Step 2**: Create admin account
     - **Step 3**: Complete installation

5. **Access the platform**
   - Homepage: `http://localhost/sja-foundation/`
   - Admin Login: Use credentials created during setup
   - Client Registration: Available from homepage

## 🔧 Configuration

### Database Configuration
The setup wizard creates a `config/database.json` file with your database settings:

```json
{
    "host": "localhost",
    "port": 3306,
    "database": "sja_foundation",
    "username": "root",
    "password": ""
}
```

### Environment Settings
System settings are stored in the database and can be modified through the admin panel:

- Site name and description
- Contact information
- Investment limits
- KYC requirements
- Referral system settings

## 📱 PWA Installation

The platform supports Progressive Web App functionality:

1. **Desktop Installation**
   - Visit the site in Chrome/Edge
   - Click the install icon in the address bar
   - Or use browser menu > "Install SJA Foundation"

2. **Mobile Installation**
   - Open in mobile browser
   - Tap browser menu
   - Select "Add to Home Screen"

## 🎨 UI/UX Features

### Glassmorphism Design
- Translucent panels with backdrop blur
- Soft shadows and rounded corners
- Smooth transitions and animations
- Floating 3D background shapes

### Dark/Light Mode
- Automatic theme detection
- Manual toggle available
- Consistent across all pages
- Saved user preference

### Responsive Design
- Mobile-first approach
- Tablet and desktop optimized
- Touch-friendly interface
- Adaptive layouts

## 🔐 Security Features

### Authentication
- Bcrypt password hashing
- Session-based authentication
- Role-based access control
- Login attempt monitoring

### Data Protection
- CSRF protection
- XSS prevention
- SQL injection prevention
- Secure file uploads

### Audit Logging
- User action tracking
- IP address logging
- Failed login attempts
- System changes audit

## 📊 Admin Features

### Dashboard
- User statistics
- Investment analytics
- Revenue tracking
- System health monitoring

### User Management
- User approval/rejection
- KYC verification
- Account status management
- Referral tree visualization

### Investment Management
- Plan creation and editing
- Investment approval
- Interest calculation
- Maturity management

### Financial Management
- Wallet operations
- Transaction monitoring
- Commission calculations
- Withdrawal processing

## 📱 Client Features

### Dashboard
- Investment overview
- Wallet balance
- Earnings summary
- Performance charts

### Investment
- Plan selection
- Amount input
- Payment processing
- Certificate generation

### KYC Verification
- Document upload
- Video KYC recording
- Status tracking
- Verification alerts

### Referral System
- Referral code sharing
- Team management
- Commission tracking
- Level progression

## 🔄 API Endpoints

### Authentication
- `POST /api/auth/login.php` - User login
- `POST /api/auth/register.php` - User registration
- `POST /api/auth/logout.php` - User logout

### Client APIs
- `GET /api/client/dashboard.php` - Dashboard data
- `GET /api/client/investments.php` - Investment list
- `GET /api/client/transactions.php` - Transaction history
- `GET /api/client/notifications.php` - Notifications

### Admin APIs
- `GET /api/admin/users.php` - User management
- `GET /api/admin/investments.php` - Investment management
- `GET /api/admin/analytics.php` - System analytics

## 🧪 Testing

### Manual Testing
1. **Setup Process**
   - Test database connection
   - Verify admin account creation
   - Check installation completion

2. **Authentication**
   - Test login/logout
   - Verify role-based access
   - Check session management

3. **Core Features**
   - Investment creation
   - KYC submission
   - Referral system
   - Notification system

### Browser Compatibility
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## 🚀 Deployment

### Production Setup
1. **Server Requirements**
   - PHP 8.0+ with required extensions
   - MySQL 8.0+ or MariaDB 10.5+
   - SSL certificate (recommended)
   - Regular backups

2. **Security Hardening**
   - Enable HTTPS
   - Configure firewall
   - Set proper file permissions
   - Regular security updates

3. **Performance Optimization**
   - Enable gzip compression
   - Configure caching headers
   - Optimize database queries
   - Use CDN for static assets

## 📝 Customization

### Branding
- Update logo and colors in CSS
- Modify company information
- Customize email templates
- Update PWA icons

### Features
- Add new investment plans
- Modify commission structure
- Customize KYC requirements
- Add payment gateways

## 🔧 Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Check MySQL service status
   - Verify credentials
   - Ensure database exists
   - Check firewall settings

2. **Installation Fails**
   - Check file permissions
   - Verify PHP extensions
   - Review error logs
   - Clear browser cache

3. **Login Issues**
   - Clear browser cookies
   - Check session configuration
   - Verify user status
   - Review audit logs

### Error Logs
- PHP errors: Check server error logs
- Database errors: Review MySQL logs
- Application errors: Check browser console

## 📞 Support

### Documentation
- API documentation available in `/docs/`
- Database schema in `/database/schema.sql`
- Configuration examples in `/config/`

### Community
- Report issues on GitHub
- Join our Discord community
- Follow updates on social media

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## 🔄 Changelog

### Version 1.0.0
- Initial release
- Complete investment management system
- MLM referral system
- KYC verification
- PWA functionality
- Glassmorphism UI

## 🎯 Roadmap

### Future Enhancements
- [ ] Mobile app (React Native)
- [ ] Payment gateway integration
- [ ] OCR-based KYC validation
- [ ] AI-powered fraud detection
- [ ] Advanced analytics dashboard
- [ ] Multi-language support
- [ ] API rate limiting
- [ ] Advanced reporting system

---

**SJA Foundation** - Secure, Transparent, and Profitable Investment Solutions

For more information, visit our [website](https://sjafoundation.com) or contact us at [info@sjafoundation.com](mailto:info@sjafoundation.com)

## Overview
A comprehensive Investment Management Platform for SJA Foundation featuring modern glassmorphism UI, MLM referral system, secure wallet management, KYC verification, and Progressive Web App (PWA) capabilities.

## Features

### Core Platform Features
- **Modern Glassmorphism UI** with day/night mode toggle
- **Progressive Web App (PWA)** with offline functionality
- **Multi-level Marketing (MLM)** referral system (11 levels)
- **Secure Authentication** with bcrypt password hashing
- **Investment Management** with multiple plans and tiers
- **KYC Verification** with document upload and video KYC
- **Wallet System** with transaction management
- **Real-time Notifications** with push notification support
- **Audit Logging** for security and compliance
- **Responsive Design** optimized for all devices

### Admin Dashboard Features
- **📊 Dashboard Overview** - Real-time statistics and analytics
- **👥 User Management** - Complete user administration
- **💰 Investment Management** - Investment plans and approvals
- **⚙️ System Settings** - Platform configuration
- **🔒 Security Settings** - Advanced security controls
- **📈 MLM Configuration** - Commission structure management
- **🔔 Notification Settings** - Email and SMS preferences
- **💾 Backup & Restore** - Database management tools

## Admin Dashboard

### Dashboard Overview
The admin dashboard provides a comprehensive view of platform statistics:

- **Total Users** - Complete user count with active/inactive breakdown
- **Investment Analytics** - Total investments, active plans, pending approvals
- **KYC Status** - Pending verifications and approval queue
- **Recent Activity** - New registrations and daily statistics

### User Management
Complete user administration interface:

**Features:**
- View all users with detailed information
- Filter by role (Admin/Client) and status
- Search functionality across user data
- Create new users with role assignment
- User status management (Active/Suspended)
- KYC status tracking and approval

**User Table Columns:**
- User profile with avatar and contact info
- Role badges (Admin/Client)
- Status indicators (Active/Inactive/Suspended)
- KYC verification status
- Referral codes for MLM tracking
- Registration dates

### Investment Management
Comprehensive investment administration:

**Investment Plans Management:**
- Create new investment plans with custom parameters
- Set minimum/maximum investment amounts
- Configure interest rates and duration
- Define plan features and benefits
- Activate/deactivate plans

**Investment Monitoring:**
- View all user investments
- Filter by status, plan, and date ranges
- Approve pending investments
- Track maturity dates and returns
- Investment performance analytics

**Investment Status Types:**
- **Pending** - Awaiting admin approval
- **Active** - Currently earning returns
- **Matured** - Completed investment term
- **Withdrawn** - Funds withdrawn by user
- **Cancelled** - Investment cancelled

### System Settings

#### General Settings
- Platform name and branding
- Admin contact information
- Currency preferences
- Platform description

#### Security Settings
- Session timeout configuration
- Maximum login attempts
- Two-factor authentication toggle
- Email verification requirements
- Strong password policy enforcement
- Audit logging controls
- IP address restrictions

#### MLM Commission Structure
Configure 11-level commission rates:
- **Level 1-3**: Primary referral commissions (3.00% - 2.00%)
- **Level 4-7**: Secondary referral commissions (1.50% - 0.75%)
- **Level 8-11**: Deep referral commissions (0.50% - 0.25%)
- Minimum withdrawal amounts
- Payout schedule configuration (Instant/Daily/Weekly/Monthly)

#### Notification Settings
**Email Notifications:**
- New user registrations
- Investment submissions
- Withdrawal requests
- KYC document uploads

**SMS Notifications:**
- Login alerts
- Transaction confirmations

**SMTP Configuration:**
- Mail server settings
- Port configuration
- Authentication credentials

#### Backup & Restore
**Database Backup:**
- Automated backup scheduling (Daily/Weekly/Monthly)
- Manual backup creation
- Backup retention policies
- Download backup files

**System Restore:**
- Upload backup files
- Restore from previous backups
- Data migration tools

## API Endpoints

### Admin API Endpoints

#### Dashboard Statistics
```
GET /api/admin/dashboard-stats.php
```
Returns comprehensive platform statistics including user counts, investment totals, and activity metrics.

#### User Management
```
GET /api/admin/users.php          # Get all users
POST /api/admin/users.php         # Create new user
```

**POST Parameters:**
- `firstName` - User's first name
- `lastName` - User's last name
- `email` - Email address
- `phone` - Phone number
- `role` - User role (admin/client)
- `password` - Account password

#### Investment Management
```
GET /api/admin/investments.php    # Get all investments
POST /api/admin/investments.php   # Approve/manage investments
```

**POST Parameters for Approval:**
- `action` - Action type (approve)
- `investment_id` - Investment ID to approve

#### Investment Plans
```
GET /api/admin/investment-plans.php    # Get all plans
POST /api/admin/investment-plans.php   # Create new plan
PUT /api/admin/investment-plans.php    # Update plan status
```

**POST Parameters:**
- `planName` - Plan name
- `description` - Plan description
- `minAmount` - Minimum investment amount
- `maxAmount` - Maximum investment amount
- `interestRate` - Annual interest rate
- `duration` - Duration in months
- `features` - Plan features (newline separated)

#### Settings Management
```
GET /api/admin/settings.php?type={category}    # Get settings by category
POST /api/admin/settings.php?type={category}   # Save settings by category
```

**Categories:**
- `general` - Platform general settings
- `security` - Security configuration
- `mlm` - MLM commission settings
- `notifications` - Notification preferences

#### Backup & Restore
```
GET /api/admin/backup.php                    # List backups
GET /api/admin/backup.php?action=download    # Download latest backup
POST /api/admin/backup.php                   # Create backup
```

**POST Body for Backup Creation:**
```json
{
    "action": "create"
}
```

## Technology Stack

### Frontend
- **HTML5** with semantic markup
- **Tailwind CSS** for styling and glassmorphism effects
- **Vanilla JavaScript** for interactivity
- **Chart.js** for data visualization
- **Service Worker** for PWA functionality

### Backend
- **PHP 7.4+** with PDO for database operations
- **MySQL 5.7+** for data storage
- **RESTful API** architecture
- **JSON** for data exchange

### Security Features
- **Bcrypt password hashing**
- **CSRF protection**
- **XSS prevention**
- **SQL injection protection**
- **Session management**
- **Audit logging**
- **Input validation and sanitization**

## Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Modern web browser

### Quick Setup
1. **Clone/Download** the project files
2. **Place files** in your web server directory
3. **Open** `setup.html` in your browser
4. **Follow the 3-step installation wizard:**
   - Database configuration
   - Admin account creation
   - System initialization

### Manual Installation
1. **Database Setup:**
   ```sql
   CREATE DATABASE sja_investment;
   USE sja_investment;
   SOURCE database/schema.sql;
   ```

2. **Configuration:**
   ```json
   {
       "host": "localhost",
       "database": "sja_investment",
       "username": "your_username",
       "password": "your_password"
   }
   ```

3. **File Permissions:**
   ```bash
   chmod 755 api/
   chmod 644 config/database.json
   chmod 755 backups/
   ```

## Database Schema

### Core Tables
- **users** - User accounts and profiles
- **investments** - Investment records
- **investment_plans** - Available investment plans
- **wallets** - User wallet balances
- **transactions** - Financial transactions
- **referrals** - MLM referral relationships
- **earnings** - Commission earnings
- **kyc_documents** - KYC verification documents
- **notifications** - System notifications
- **settings** - Platform configuration
- **audit_logs** - Security audit trail

### MLM Structure
- **11-level deep referral tracking**
- **Commission calculation engine**
- **Automated payout processing**
- **Referral performance analytics**

## Usage

### Admin Access
1. **Login** with admin credentials
2. **Navigate** through the sidebar menu
3. **Manage** users, investments, and settings
4. **Monitor** platform performance and statistics

### Client Features
- **Investment portfolio management**
- **Referral system participation**
- **KYC document submission**
- **Wallet and transaction history**
- **Real-time notifications**

## Security Considerations

### Data Protection
- All passwords are hashed using bcrypt
- Sensitive data is encrypted in transit
- Database connections use prepared statements
- Input validation prevents injection attacks

### Access Control
- Role-based permissions (Admin/Client)
- Session timeout management
- IP address restrictions available
- Audit logging for compliance

### Best Practices
- Regular security updates
- Database backup automation
- SSL/TLS encryption recommended
- Strong password policies enforced

## Troubleshooting

### Common Issues

**Installation Problems:**
- Verify PHP version compatibility
- Check MySQL connection settings
- Ensure proper file permissions
- Review error logs for details

**Database Connection:**
- Verify credentials in `config/database.json`
- Test connection using `api/setup/test-connection.php`
- Check MySQL service status

**Admin Dashboard Access:**
- Clear browser cache and cookies
- Verify admin user exists in database
- Check session configuration

### Error Logging
- PHP errors logged to server error log
- Database errors returned in API responses
- Audit logs track user actions
- Browser console shows JavaScript errors

## Contributing

### Development Guidelines
- Follow PSR coding standards
- Use meaningful variable names
- Add comments for complex logic
- Test all functionality thoroughly

### Database Changes
- Update schema.sql for new tables
- Create migration scripts if needed
- Document schema changes
- Test with sample data

## License

This project is developed for SJA Foundation. All rights reserved.

## Support

For technical support or questions:
- **Email:** admin@sjafoundation.com
- **Phone:** +91-9876543210
- **Documentation:** This README file

---

**Version:** 2.0.0  
**Last Updated:** December 2024  
**Platform:** Investment Management System with MLM #   S m a r t - I n v e s t m e n t - M a n a g e m e n t - P l a t f o r m  
 