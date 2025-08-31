# Notification System Documentation

## Overview
The notification system automatically sends notifications to all users when someone reports a lost or found item. This helps keep the community informed about new items in the system.

## How It Works

### 1. Automatic Notifications
When a user reports a lost or found item:
- The system creates a new item in the database
- It automatically sends notifications to all active users (except the person who reported the item)
- Each notification includes:
  - Item name
  - Item type (lost/found)
  - Location where the item was lost/found
  - Link to view the item details

### 2. Notification Types
The system supports different types of notifications:
- **System**: General notifications about new items
- **Claim**: When someone claims an item
- **Match**: When there's a potential match
- **Status Update**: When item status changes
- **Reward**: When rewards are awarded

### 3. Notification Display
- Notifications appear in the navigation bar with a red badge showing unread count
- Users can view all notifications on the notifications page
- Notifications can be marked as read individually or all at once
- Unread notifications are highlighted with a different background color

## Files Modified

### Core Files
- `config.php` - Added notification helper functions
- `report_lost.php` - Updated to use new notification system
- `report_found.php` - Updated to use new notification system
- `notifications.php` - Updated to use helper functions
- `home.php` - Updated to use helper functions

### New Files
- `test_notifications.php` - Test page for the notification system
- `NOTIFICATION_SYSTEM.md` - This documentation

## Helper Functions

### `sendNotificationToAllUsers($conn, $sender_id, $item_id, $item_name, $item_type, $location)`
Sends notifications to all active users except the sender.

**Parameters:**
- `$conn` - Database connection
- `$sender_id` - User ID of the person who reported the item
- `$item_id` - ID of the reported item
- `$item_name` - Name of the item
- `$item_type` - Type of item ('lost' or 'found')
- `$location` - Location where item was lost/found

**Returns:** `true` if successful, `false` if failed

### `sendNotificationToUser($conn, $user_id, $item_id, $type, $message)`
Sends a notification to a specific user.

**Parameters:**
- `$conn` - Database connection
- `$user_id` - Target user ID
- `$item_id` - Related item ID
- `$type` - Notification type
- `$message` - Notification message

**Returns:** `true` if successful, `false` if failed

### `getUnreadNotificationCount($conn, $user_id)`
Gets the count of unread notifications for a user.

**Parameters:**
- `$conn` - Database connection
- `$user_id` - User ID

**Returns:** Number of unread notifications

### `markNotificationsAsRead($conn, $user_id, $notification_ids = null)`
Marks notifications as read.

**Parameters:**
- `$conn` - Database connection
- `$user_id` - User ID
- `$notification_ids` - Array of notification IDs (optional, if null marks all as read)

**Returns:** `true` if successful, `false` if failed

## Database Schema

The notifications are stored in the `notifications` table:

```sql
CREATE TABLE notifications (
    Notification_ID INT AUTO_INCREMENT PRIMARY KEY,
    User_ID VARCHAR(7),
    Item_ID INT,
    Type ENUM('claim', 'match', 'status_update', 'reward', 'system') NOT NULL,
    Message TEXT NOT NULL,
    Is_Read BOOLEAN DEFAULT FALSE,
    Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (User_ID) REFERENCES user(User_ID) ON DELETE CASCADE,
    FOREIGN KEY (Item_ID) REFERENCES items(Item_ID) ON DELETE CASCADE
);
```

## Testing

To test the notification system:

1. Access `test_notifications.php` (requires login)
2. Click "Send Test Notification to All Users"
3. Check that all users receive the notification
4. Verify the notification appears in the navigation badge
5. Check the notifications page to see the new notification

## Features

### ✅ Implemented
- Automatic notifications when items are reported
- Notification badges in navigation
- Notification management (mark as read)
- Different notification types
- Error handling and logging
- Helper functions for reusability

### 🔄 Future Enhancements
- Email notifications
- Push notifications
- Notification preferences
- Notification filtering
- Real-time notifications using WebSockets

## Usage Examples

### Sending a notification when reporting a lost item:
```php
$notification_sent = sendNotificationToAllUsers($conn, $user_id, $item_id, $item_name, 'lost', $location);
```

### Getting notification count for display:
```php
$notification_count = getUnreadNotificationCount($conn, $user_id);
```

### Marking all notifications as read:
```php
markNotificationsAsRead($conn, $user_id);
```

## Error Handling

The notification system includes comprehensive error handling:
- Database errors are logged to `error.log`
- Failed notifications don't prevent item creation
- Graceful fallbacks when notification system is unavailable
- User-friendly error messages

## Security Considerations

- Only active users receive notifications
- Users cannot send notifications to themselves
- All database queries use prepared statements
- Input is properly sanitized and validated
- Notifications are tied to specific items and users 