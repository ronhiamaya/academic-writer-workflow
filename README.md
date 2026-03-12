# Academic Writer Workflow for WordPress

A powerful WordPress plugin that seamlessly connects WooCommerce orders with project management tools to create a complete academic writing management system. Perfect for essay writing, dissertation, and term paper services.

## 🚀 Features

### Complete Order Management
- Custom WooCommerce product type for academic papers
- Collect detailed order information (topic, academic level, pages, deadline)
- Automatic project creation upon payment completion
- Price-per-page calculation system

### Seamless Integration
- **WooCommerce** - Robust shopping cart and payment processing
- **WPNakama** - Project management and writer collaboration
- Support for multiple payment gateways (PayPal, Stripe, Cards)
- Client and writer dashboards

### Academic-Specific Functionality
- Academic level selection (High School, College, University, Master's, PhD)
- Deadline tracking with datetime picker
- Automatic task creation for writers
- Order-to-project synchronization

## 📋 Requirements

- WordPress 5.0 or higher
- WooCommerce 5.0 or higher
- WPNakama (free version available) or compatible project management plugin
- PHP 7.4 or higher

## 🔧 Installation

### Method 1: Plug-and-Play (Recommended)
1. Download the plugin zip file
2. Go to WordPress Admin > Plugins > Add New
3. Click "Upload Plugin" and select the zip file
4. Click "Install Now" and then "Activate"

### Method 2: Manual Installation
1. Upload the `academic-writer-workflow` folder to `/wp-content/plugins/`
2. Go to WordPress Admin > Plugins
3. Find "Academic Writer Workflow" in the list and click "Activate"

## ⚙️ Configuration

### Step 1: Install Required Plugins
Make sure these plugins are installed and activated:
- WooCommerce
- WPNakama (or your chosen project management plugin)

### Step 2: Create Academic Products
1. Go to **Products > Add New**
2. Enter a product name (e.g., "Essay Writing Service")
3. In Product Data dropdown, select **"Academic Paper"**
4. Go to the **"Academic Settings"** tab
5. Set default academic level and price per page
6. Publish the product

### Step 3: Configure Pricing Rules (Optional)
Use a WooCommerce dynamic pricing plugin to set different rates based on:
- Academic level (High School vs PhD pricing)
- Deadline urgency
- Number of pages

### Step 4: Test the Workflow
1. Place a test order on your site
2. Complete the payment
3. Check that a project is automatically created in WPNakama
4. Verify all order details are transferred correctly

## 🎯 How It Works

### For Clients
1. Browse academic services
2. Select paper type and fill in details (topic, pages, deadline)
3. Checkout and pay securely
4. Track progress in their client dashboard

### For Admins
1. View all orders in WooCommerce
2. Access academic details in order metabox
3. Monitor automatically created projects
4. Manage writers and disputes from one dashboard

### For Writers
1. See assigned tasks in WPNakama
2. Access order details and requirements
3. Submit completed work
4. Communicate with clients

## 🔌 Customization

### Adding New Academic Levels
Edit the `awf_render_metabox()` function in the plugin file to add or modify academic levels:

```php
<option value="new_level">New Level Name</option>
