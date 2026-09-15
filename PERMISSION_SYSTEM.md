# Permission System Implementation

## Overview
The permission system now follows the flow: `User → Staff → Role → Permissions (view, create, edit, delete)`

## Permission Flow

### 1. User Roles
- **superadmin**: Full system access
- **shop_owner**: Full access to their shop's data
- **staff**: Limited access based on assigned role and branch
- **branch_head**: Staff member with additional branch management responsibilities

### 2. Permission Structure
- **Modules**: Top-level categories (Employees, Branches, Products, Sales, Expenses)
- **Features**: Specific functionality within modules
- **Permissions**: Granular actions (view, create, edit, delete)

### 3. Permission Naming Convention
Format: `{feature}.{action}`
Examples:
- `employees.view` - View employee list
- `employees.create` - Create new employee
- `employees.edit` - Edit employee details
- `employees.delete` - Delete employee
- `branches.view` - View branch list
- `branches.create` - Create new branch
- `branches.edit` - Edit branch details
- `branches.delete` - Delete branch

## Route Protection

### Employee Routes
```php
Route::middleware(['can:employees.view'])->get('/employees', [EmployeeController::class, 'index']);
Route::middleware(['can:employees.create'])->post('/employees', [EmployeeController::class, 'store']);
Route::middleware(['can:employees.edit'])->put('/employees/{staff}', [EmployeeController::class, 'update']);
Route::middleware(['can:employees.edit'])->post('/employees/{staff}/reset-password', [EmployeeController::class, 'resetPassword']);
Route::middleware(['can:employees.delete'])->delete('/employees/{staff}', [EmployeeController::class, 'destroy']);
```

### Branch Routes
```php
Route::middleware(['can:branches.view'])->get('/branches', [BranchController::class, 'index']);
Route::middleware(['can:branches.create'])->post('/branches', [BranchController::class, 'store']);
Route::middleware(['can:branches.edit'])->put('/branches/{branch}', [BranchController::class, 'update']);
Route::middleware(['can:branches.delete'])->delete('/branches/{branch}', [BranchController::class, 'destroy']);
```

## Branch Head Visibility

### Automatic Branch Scoping
The `BelongsToBranch` trait automatically scopes queries based on user role:
- **superadmin/shop_owner**: No scoping - sees all data in their shop
- **branch_head**: Only sees data for their assigned branch
- **staff**: Only sees data for their assigned branch

### Implementation Details
```php
// In Staff model (already implemented)
use App\Models\Concerns\BelongsToBranch;

class Staff extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, BelongsToBranch;
    // ...
}
```

### Controller-Level Branch Restrictions
Additional checks in controllers ensure branch heads can only operate within their branch:

**EmployeeController:**
- Branch heads can only view/edit/delete staff in their branch
- Branch heads can only reset passwords for staff in their branch

**BranchController:**
- Branch heads can only view/update their own branch
- Branch heads cannot create or delete branches
- Branch heads can only see their branch in the index

## Database Seeding

### New Seeders Added
1. **ModuleSeeder**: Creates module categories (Employees, Branches, Products, Sales, Expenses)
2. **FeatureSeeder**: Creates features for each module and generates permissions (view, create, edit, delete)

### Running Seeders
```bash
php artisan db:seed --class=ModuleSeeder
php artisan db:seed --class=FeatureSeeder
```

Or run all seeders:
```bash
php artisan db:seed
```

## Security Improvements

### 1. Route-Level Permission Checks
- Added `can:` middleware to all employee and branch routes
- Ensures users have specific permissions before accessing endpoints

### 2. Controller-Level Authorization
- Added shop ownership verification
- Added branch-level restrictions for branch heads
- Prevents privilege escalation across branches

### 3. Input Sanitization
- Fixed SQL injection vulnerability in search functionality
- Added proper escaping for LIKE queries

### 4. Permission Intersection Validation
- RoleController prevents granting permissions from modules outside the shop's plan
- Prevents privilege escalation through permission assignment

## Testing the Permission System

### 1. Seed the Database
```bash
php artisan db:seed
```

### 2. Create Roles and Assign Permissions
Use the RoleController endpoints:
- `POST /roles` - Create new role
- `PUT /roles/{role}/permissions` - Assign permissions to role
- `GET /roles/{role}/matrix` - View permission matrix

### 3. Assign Role to Staff
```php
$staff->user->assignRole('branch_head');
```

### 4. Test Branch Head Visibility
- Login as a branch head
- Access `/employees` - should only see staff in their branch
- Access `/branches` - should only see their branch
- Try to access another branch's data - should be denied

## Key Changes Made

### Routes (api.php)
- Added permission middleware to all employee and branch routes
- Ensures fine-grained access control

### Controllers
- **EmployeeController**: Added branch-level restrictions for branch heads, fixed search injection
- **BranchController**: Added branch-level restrictions, prevented branch heads from creating/deleting branches
- **RoleController**: Already had proper permission intersection validation

### Models
- **Staff**: Already uses BelongsToBranch trait for automatic scoping
- **Branch**: No changes needed (parent entity)

### Database
- Added ModuleSeeder and FeatureSeeder
- Updated DatabaseSeeder to include new seeders

## Next Steps

1. **Test the permission system** with different user roles
2. **Create additional seeders** for default roles and permissions
3. **Add API tests** to verify permission checks work correctly
4. **Consider adding permission caching** for performance optimization
5. **Implement frontend permission checks** to hide/disable UI elements based on permissions
