<?php
if(session_status() == PHP_SESSION_NONE){
    session_start();
}

include __DIR__ . '/txt.php';

/*
|--------------------------------------------------------------------------
| BASE URL DETECTION
|--------------------------------------------------------------------------
| This automatically handles:
| - root pages
| - pages folder
| - includes folder
*/

$current_script = $_SERVER['PHP_SELF'];

if(strpos($current_script, '/pages/') !== false){
    $base_url = '../';
}else{
    $base_url = '';
}

// Get admin name from session
$admin_name = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : 'Administrator';
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">

    <title><?php echo $software_name; ?></title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Quicksand:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- MAIN CSS -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/style.css">

    <!-- Mobile Menu Toggle Button CSS -->
    <style>
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            color: #A04657;
            cursor: pointer;
            padding: 8px;
            margin-right: 15px;
            border-radius: 8px;
        }
        
        .mobile-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1040;
        }
        
        .mobile-overlay.active {
            display: block;
        }
        
        .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .sidebar-close {
            display: none;
            background: none;
            border: none;
            font-size: 20px;
            color: #666;
            cursor: pointer;
            padding: 8px;
        }
        
        .sidebar-divider {
            height: 1px;
            background: #eef2f6;
            margin: 10px 20px;
        }
        
        /* Submenu styles */
        .sidebar-menu .has-submenu {
            position: relative;
        }
        
        .sidebar-menu .submenu {
            list-style: none;
            padding-left: 45px;
            display: none;
            margin: 5px 0;
        }
        
        .sidebar-menu .submenu.show {
            display: block;
        }
        
        .sidebar-menu .submenu li a {
            padding: 8px 15px;
            font-size: 13px;
            color: #666;
            display: block;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .sidebar-menu .submenu li a:hover {
            background: #f0f0f0;
            color: #A04657;
            padding-left: 20px;
        }
        
        .sidebar-menu .submenu li a i {
            margin-right: 10px;
            font-size: 12px;
        }
        
        .toggle-submenu {
            float: right;
            cursor: pointer;
        }
        
        @media (max-width: 991px) {
            .mobile-menu-toggle {
                display: block;
            }
            
            .fixed-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                position: fixed;
                top: 0;
                left: 0;
                width: 280px;
                height: 100%;
                z-index: 1050;
                background: white;
                box-shadow: 2px 0 12px rgba(0,0,0,0.15);
            }
            
            .fixed-sidebar.mobile-open {
                transform: translateX(0) !important;
            }
            
            .sidebar-close {
                display: block;
            }
            
            .main-content {
                margin-left: 0 !important;
                margin-top: 65px;
                padding: 12px;
                min-height: calc(100vh - 65px);
                box-sizing: border-box;
            }

            .main-wrapper,
            .dashboard-wrapper {
                margin: 0;
                padding: 0;
                width: 100%;
            }
            
            .fixed-header {
                position: fixed;
                top: 0;
                left: 0 !important;
                width: 100% !important;
                z-index: 1030;
            }
        }
        
        @media (min-width: 992px) {
            .fixed-sidebar {
                transform: translateX(0) !important;
                position: fixed;
                top: 0;
                left: 0;
                width: 260px;
                height: 100%;
                overflow-y: auto;
            }
            
            .sidebar-close {
                display: none;
            }
            
            .main-content {
                margin-left: 260px;
                margin-top: 65px;
                padding: 18px 24px;
                min-height: calc(100vh - 65px);
                box-sizing: border-box;
            }

            .main-wrapper,
            .dashboard-wrapper {
                margin: 0;
                padding: 0;
                width: 100%;
            }

            .fixed-header {
                left: 260px;
                width: calc(100% - 260px);
            }
        }
        
        /* Scrollbar styling */
        .fixed-sidebar::-webkit-scrollbar {
            width: 5px;
        }
        
        .fixed-sidebar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .fixed-sidebar::-webkit-scrollbar-thumb {
            background: #A04657;
            border-radius: 5px;
        }
    </style>

</head>

<body>

<!-- =========================================================
     HEADER (Fixed Top)
========================================================= -->

<div class="fixed-header">

    <div class="header-container">

        <!-- Mobile Menu Toggle Button -->
        <button class="mobile-menu-toggle" id="mobileMenuToggle">
            <i class="fas fa-bars"></i>
        </button>

        <!-- COMPANY LOGO -->
        <div class="logo-area">
            <h4>
                <?php echo $company_name; ?>
            </h4>
        </div>

        <!-- USER AREA - Only Admin Name and Logout -->
        <div class="user-area">
            <span>
                <i class="fas fa-user-circle"></i>
                <?php echo $admin_name; ?>
            </span>
            <a href="<?php echo $base_url; ?>logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

    </div>

</div>

<!-- Mobile Overlay -->
<div class="mobile-overlay" id="mobileOverlay"></div>

<!-- =========================================================
     SIDEBAR
========================================================= -->

<div class="fixed-sidebar" id="mainSidebar">

    <!-- BRAND -->
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <h3>
                <?php echo $software_name; ?>
            </h3>
        </div>
        <button class="sidebar-close" id="sidebarClose">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- MENU -->
    <ul class="sidebar-menu">

        <!-- DASHBOARD -->
        <?php if(!is_salesman()): ?>
        <li>
            <a href="<?php echo $base_url; ?>index.php">
                <i class="fas fa-chart-pie"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <!-- CUSTOMER MANAGEMENT SECTION -->
        <li class="sidebar-divider"></li>
        <li class="sidebar-title">Customer Management</li>
        
        <!-- CUSTOMERS with Submenu -->
        <li class="has-submenu">
            <a href="javascript:void(0)" class="submenu-trigger">
                <i class="fas fa-users"></i>
                <span>Customers</span>
                <i class="fas fa-chevron-down toggle-submenu"></i>
            </a>
            <ul class="submenu">
                <li>
                    <a href="<?php echo $base_url; ?>pages/customer_view.php">
                        <i class="fas fa-eye"></i> View Customer
                    </a>
                </li>
                <li>
                    <a href="<?php echo $base_url; ?>pages/ledger.php">
                        <i class="fas fa-book"></i> Customer Ledger
                    </a>
                </li>
                <li>
                    <a href="<?php echo $base_url; ?>pages/payments.php">
                        <i class="fas fa-money-bill-wave"></i> Payments
                    </a>
                </li>
                <li>
                    <a href="<?php echo $base_url; ?>pages/customer_list.php">
                        <i class="fas fa-id-card"></i> Customer ID List
                    </a>
                </li>
            </ul>
        </li>
        <?php endif; ?>

        <!-- DELIVERIES with Submenu -->
        <li class="has-submenu">
            <a href="javascript:void(0)" class="submenu-trigger">
                <i class="fas fa-truck"></i>
                <span>Daily Delivery</span>
                <i class="fas fa-chevron-down toggle-submenu"></i>
            </a>
            <ul class="submenu">
                <li>
                    <a href="<?php echo $base_url; ?>pages/deliveries.php">
                        <i class="fas fa-pen"></i> Entry Point
                    </a>
                </li>
                <li>
                    <a href="<?php echo $base_url; ?>pages/delivery_view.php">
                        <i class="fas fa-eye"></i> View Point
                    </a>
                </li>
            </ul>
        </li>
        
        <!-- RETURN -->
        <?php if(!is_salesman()): ?>
        <li>
            <a href="<?php echo $base_url; ?>pages/empty_bottle_return.php">
                <i class="fas fa-undo-alt"></i>
                <span>Bottle Tracking</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if(!is_salesman()): ?>

        <!-- PRODUCT STOCK -->
        <li class="has-submenu">
            <a href="javascript:void(0)" class="submenu-trigger">
                <i class="fas fa-boxes"></i>
                <span>Product Stock</span>
                <i class="fas fa-chevron-down toggle-submenu"></i>
            </a>
            <ul class="submenu">
                <li>
                    <a href="<?php echo $base_url; ?>pages/stock.php">
                        <i class="fas fa-list"></i> Stock Levels
                    </a>
                </li>
                <li>
                    <a href="<?php echo $base_url; ?>pages/stock_ledger.php">
                        <i class="fas fa-history"></i> Stock Ledger
                    </a>
                </li>
                <li>
                    <a href="<?php echo $base_url; ?>pages/product_list.php">
                        <i class="fas fa-id-card"></i> Product ID List
                    </a>
                </li>
            </ul>
        </li>



        <!-- RAW MATERIAL & SUPPLIER SECTION -->
        <li class="sidebar-divider"></li>
        <li class="sidebar-title">Raw Material Management</li>

        <!-- SUPPLIERS with Submenu -->
        <li class="has-submenu">
            <a href="javascript:void(0)" class="submenu-trigger">
                <i class="fas fa-truck"></i>
                <span>Suppliers</span>
                <i class="fas fa-chevron-down toggle-submenu"></i>
            </a>
            <ul class="submenu">
                <li>
                    <a href="<?php echo $base_url; ?>pages/suppliers.php">
                        <i class="fas fa-list"></i> Supplier List
                    </a>
                </li>
                <li>
                    <a href="<?php echo $base_url; ?>pages/supplier_ledger.php">
                        <i class="fas fa-book"></i> Supplier Ledger
                    </a>
                </li>
                <li>
                    <a href="<?php echo $base_url; ?>pages/supplier_payment.php">
                        <i class="fas fa-money-bill-wave"></i> Supplier Payment
                    </a>
                </li>
                <li>
                    <a href="<?php echo $base_url; ?>pages/supplier_list.php">
                        <i class="fas fa-id-card"></i> Supplier ID List
                    </a>
                </li>
            </ul>
        </li>

        <!-- RAW MATERIAL STOCK with Submenu -->
        <li class="has-submenu">
            <a href="javascript:void(0)" class="submenu-trigger">
                <i class="fas fa-boxes"></i>
                <span>Raw Material</span>
                <i class="fas fa-chevron-down toggle-submenu"></i>
            </a>
            <ul class="submenu">
                <li>
                    <a href="<?php echo $base_url; ?>pages/raw_material_stock.php">
                        <i class="fas fa-database"></i> Stock Management
                    </a>
                </li>
                <li>
                    <a href="<?php echo $base_url; ?>pages/raw_material_purchase.php">
                        <i class="fas fa-shopping-cart"></i> Purchase Material
                    </a>
                </li>
                <li>
                    <a href="<?php echo $base_url; ?>pages/raw_material_issuance.php">
                        <i class="fas fa-sign-out-alt"></i> Issue Material
                    </a>
                </li>
                <li>
                    <a href="<?php echo $base_url; ?>pages/purchase_list.php">
                        <i class="fas fa-history"></i> Purchase History
                    </a>
                </li>
            </ul>
        </li>

        <!-- STOCK REPORTS -->
        <li>
            <a href="<?php echo $base_url; ?>pages/raw_material_stock_report.php">
                <i class="fas fa-chart-line"></i>
                <span>Stock Report</span>
            </a>
        </li>

        <!-- FINANCIAL SECTION -->
        <li class="sidebar-divider"></li>
        <li class="sidebar-title">Financial Management</li>

        <!-- EXPENSES -->
        <li class="has-submenu">
            <a href="javascript:void(0)" class="submenu-trigger">
                <i class="fas fa-money-bill-wave"></i>
                <span>Expenses</span>
                <i class="fas fa-chevron-down toggle-submenu"></i>
            </a>
            <ul class="submenu">
                <li>
                    <a href="<?php echo $base_url; ?>pages/expenses.php">
                        <i class="fas fa-list-alt"></i> Expense Entry
                    </a>
                </li>
                <li>
                    <a href="<?php echo $base_url; ?>pages/expense_categories.php">
                        <i class="fas fa-tags"></i> Expense Categories
                    </a>
                </li>
            </ul>
        </li>

        <!-- CASHBOOK -->
        <li>
            <a href="<?php echo $base_url; ?>pages/cashbook.php">
                <i class="fas fa-book"></i>
                <span>Cashbook</span>
            </a>
        </li>

        <!-- PROFIT & LOSS -->
        <li>
            <a href="<?php echo $base_url; ?>pages/profit_loss.php">
                <i class="fas fa-chart-pie"></i>
                <span>Profit & Loss</span>
            </a>
        </li>        

        <!-- GENERAL REPORTS SECTION -->
        <li class="sidebar-divider"></li>
        <li class="sidebar-title">Reports</li>

        <!-- REPORTS -->
        <li>
            <a href="<?php echo $base_url; ?>pages/reports.php">
                <i class="fas fa-chart-line"></i>
                <span>All Reports</span>
            </a>
        </li>

        <!-- VOUCHER SEARCH -->
        <li>
            <a href="<?php echo $base_url; ?>pages/voucher_search.php">
                <i class="fas fa-search"></i>
                <span>Voucher Search</span>
            </a>
        </li>

        <?php endif; ?>

        <?php if(is_admin()): ?>

        <!-- SYSTEM SECTION -->
        <li class="sidebar-divider"></li>
        <li class="sidebar-title">System</li>

        <!-- USERS -->
        <li>
            <a href="<?php echo $base_url; ?>pages/users.php">
                <i class="fas fa-user-cog"></i>
                <span>Users</span>
            </a>
        </li>

        <?php endif; ?>

    </ul>

</div>

<!-- =========================================================
     MAIN CONTENT START
========================================================= -->

<div class="main-content">

<script>
// Mobile sidebar toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const mobileToggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.getElementById('mainSidebar');
    const overlay = document.getElementById('mobileOverlay');
    const closeBtn = document.getElementById('sidebarClose');
    
    if(mobileToggle) {
        mobileToggle.addEventListener('click', function() {
            sidebar.classList.add('mobile-open');
            if(overlay) overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
    }
    
    if(closeBtn) {
        closeBtn.addEventListener('click', function() {
            sidebar.classList.remove('mobile-open');
            if(overlay) overlay.classList.remove('active');
            document.body.style.overflow = '';
        });
    }
    
    if(overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        });
    }
    
    // Close sidebar when window resizes above mobile breakpoint
    window.addEventListener('resize', function() {
        if(window.innerWidth > 991) {
            sidebar.classList.remove('mobile-open');
            if(overlay) overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
    
    // Active menu highlighting
    const currentPage = window.location.pathname.split('/').pop();
    const links = document.querySelectorAll('.sidebar-menu a');
    links.forEach(link => {
        const href = link.getAttribute('href');
        if(href && !href.includes('javascript:void(0)')){
            const linkPage = href.split('/').pop();
            if(linkPage === currentPage) {
                link.classList.add('active');
                // Expand parent submenu if this is a submenu item
                const parentSubmenu = link.closest('.submenu');
                if(parentSubmenu) {
                    parentSubmenu.classList.add('show');
                    const parentTrigger = parentSubmenu.closest('.has-submenu').querySelector('.submenu-trigger');
                    if(parentTrigger) {
                        parentTrigger.classList.add('active');
                        const icon = parentTrigger.querySelector('.toggle-submenu');
                        if(icon) icon.classList.remove('fa-chevron-down');
                        if(icon) icon.classList.add('fa-chevron-up');
                    }
                }
            }
        }
    });
    
    // Submenu toggle functionality
    const submenuTriggers = document.querySelectorAll('.submenu-trigger');
    submenuTriggers.forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            const parent = this.closest('.has-submenu');
            const submenu = parent.querySelector('.submenu');
            const icon = this.querySelector('.toggle-submenu');
            
            // Toggle submenu
            submenu.classList.toggle('show');
            
            // Change icon
            if(icon.classList.contains('fa-chevron-down')) {
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            } else {
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            }
        });
    });
});

// Add this CSS for sidebar titles
const style = document.createElement('style');
style.textContent = `
    .sidebar-title {
        padding: 10px 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        color: #A04657;
        letter-spacing: 0.5px;
        margin-top: 10px;
    }
    .sidebar-divider {
        height: 1px;
        background: #eef2f6;
        margin: 10px 20px;
    }
    .sidebar-menu .has-submenu .submenu-trigger.active {
        background: #A04657;
        color: white;
    }
    .sidebar-menu .has-submenu .submenu-trigger.active i {
        color: white;
    }
`;
document.head.appendChild(style);
</script>