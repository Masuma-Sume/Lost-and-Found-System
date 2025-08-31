<?php
require_once 'config.php';
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = $error_message = '';

// Get unread notifications count
$notifications = getUnreadNotificationCount($conn, $user_id) ?? 0;

try {
    // Get claims made by the user
    $my_claims_sql = "SELECT c.*, i.Item_Name, i.Item_Type, i.Location, i.Date_Lost_Found, i.Image_URL, 
                            u.Name as Reporter_Name, u.Contact_No as Reporter_Contact
                     FROM claims c
                     JOIN items i ON c.Item_ID = i.Item_ID
                     JOIN user u ON i.User_ID = u.User_ID
                     WHERE c.Claimant_ID = ?
                     ORDER BY c.Created_At DESC";
    $stmt = executeQuery($conn, $my_claims_sql, [$user_id], 's');
    $my_claims = $stmt->get_result();
    
    // Get claims for items reported by the user
    $received_claims_sql = "SELECT c.*, i.Item_Name, i.Item_Type, i.Location, i.Date_Lost_Found, i.Image_URL, 
                                  u.Name as Claimant_Name, u.Contact_No as Claimant_Contact
                           FROM claims c
                           JOIN items i ON c.Item_ID = i.Item_ID
                           JOIN user u ON c.Claimant_ID = u.User_ID
                           WHERE i.User_ID = ?
                           ORDER BY c.Created_At DESC";
    $stmt = executeQuery($conn, $received_claims_sql, [$user_id], 's');
    $received_claims = $stmt->get_result();
    
    // Handle claim status updates
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['claim_id'])) {
        $claim_id = intval($_POST['claim_id']);
        $action = $_POST['action'];
        $admin_notes = isset($_POST['admin_notes']) ? trim($_POST['admin_notes']) : '';
        
        // Verify the claim belongs to an item reported by this user
        $verify_sql = "SELECT c.*, i.Item_ID, i.Item_Name, i.User_ID as Reporter_ID, c.Claimant_ID 
                       FROM claims c
                       JOIN items i ON c.Item_ID = i.Item_ID
                       WHERE c.Claim_ID = ? AND i.User_ID = ?";
        $stmt = executeQuery($conn, $verify_sql, [$claim_id, $user_id], 'is');
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error_message = "You don't have permission to update this claim.";
        } else {
            $claim_data = $result->fetch_assoc();
            $new_status = '';
            $item_id = $claim_data['Item_ID'];
            $item_name = $claim_data['Item_Name'];
            $claimant_id = $claim_data['Claimant_ID'];
            
            switch ($action) {
                case 'approve':
                    $new_status = 'approved';
                    // Update item status to claimed
                    $update_item_sql = "UPDATE items SET Status = 'claimed' WHERE Item_ID = ?";
                    executeQuery($conn, $update_item_sql, [$item_id], 'i');
                    break;
                case 'reject':
                    $new_status = 'rejected';
                    break;
                default:
                    $error_message = "Invalid action.";
                    break;
            }
            
            if (!empty($new_status)) {
                // Update claim status
                $update_sql = "UPDATE claims SET Claim_Status = ?, Admin_Notes = ? WHERE Claim_ID = ?";
                executeQuery($conn, $update_sql, [$new_status, $admin_notes, $claim_id], 'ssi');
                
                // Send notification to claimant
                $notification_message = "Your claim for \"$item_name\" has been $new_status.";
                if (!empty($admin_notes)) {
                    $notification_message .= " Note: $admin_notes";
                }
                sendNotificationToUser($conn, $claimant_id, $item_id, 'status_update', $notification_message);
                
                $success_message = "Claim has been $new_status successfully.";
                
                // Refresh the claims lists
                $stmt = executeQuery($conn, $received_claims_sql, [$user_id], 's');
                $received_claims = $stmt->get_result();
            }
        }
    }
    
} catch (Exception $e) {
    error_log("My claims error: " . $e->getMessage());
    $error_message = "An error occurred while loading your claims.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Claims</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --primary-color: #009688; /* Teal */
            --primary-dark: #00796B; /* Dark Teal */
            --secondary-color: #ffffff;
            --text-color: #222;
            --text-light: #666;
            --border-color: #e0e0e0;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Merriweather', serif; background: #f5f5f5; margin: 0; padding: 0; color: var(--text-color); }
        .top-bar { background: var(--primary-color); color: white; padding: 1rem; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.5rem; font-weight: bold; }
        .nav-menu { display: flex; gap: 1.5rem; align-items: center; }
        .nav-item { color: white; text-decoration: none; padding: 0.5rem 1rem; border-radius: 50px; transition: all 0.3s ease; display: flex; align-items: center; gap: 0.5rem; }
        .nav-item:hover { background-color: rgba(255,255,255,0.1); }
        .notification-badge { background-color: #ff5722; color: white; border-radius: 50%; padding: 0.25rem 0.5rem; font-size: 0.75rem; margin-left: 0.25rem; font-weight: bold; }
        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        h1 { color: var(--primary-color); text-align: center; margin-bottom: 30px; }
        .tabs { display: flex; margin-bottom: 30px; border-bottom: 2px solid var(--border-color); }
        .tab { padding: 15px 30px; cursor: pointer; font-weight: bold; color: var(--text-light); }
        .tab.active { color: var(--primary-color); border-bottom: 3px solid var(--primary-color); margin-bottom: -2px; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .success { background: #e0f7fa; color: #00796B; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center; }
        .error { background: #ffebee; color: #c62828; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center; }
        .claim-card { background: white; border-radius: 8px; box-shadow: var(--shadow); margin-bottom: 20px; overflow: hidden; }
        .claim-header { padding: 15px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
        .claim-title { font-size: 1.2rem; font-weight: bold; color: var(--primary-color); }
        .claim-status { padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; }
        .status-pending { background: var(--warning-color); color: #212529; }
        .status-approved { background: var(--success-color); color: white; }
        .status-rejected { background: var(--danger-color); color: white; }
        .status-verified { background: var(--info-color); color: white; }
        .claim-body { padding: 20px; display: flex; gap: 20px; }
        .claim-image { width: 150px; height: 150px; object-fit: cover; border-radius: 8px; }
        .claim-image-placeholder { width: 150px; height: 150px; background: #eee; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #aaa; }
        .claim-details { flex: 1; }
        .claim-property { margin-bottom: 10px; }
        .claim-label { font-weight: bold; color: var(--text-light); margin-bottom: 3px; }
        .claim-value { }
        .claim-description { background: #f9f9f9; padding: 15px; border-radius: 5px; margin-top: 15px; }
        .claim-actions { padding: 15px; border-top: 1px solid var(--border-color); display: flex; gap: 10px; justify-content: flex-end; }
        .btn { display: inline-block; padding: 8px 16px; border-radius: 4px; font-size: 0.9rem; cursor: pointer; text-decoration: none; border: none; }
        .btn-primary { background: var(--primary-color); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-success { background: var(--success-color); color: white; }
        .btn-danger { background: var(--danger-color); color: white; }
        .btn-secondary { background: #757575; color: white; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 10% auto; padding: 20px; border-radius: 8px; width: 500px; max-width: 90%; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-title { font-size: 1.2rem; color: var(--primary-color); }
        .close-modal { font-size: 1.5rem; cursor: pointer; }
        .modal-body { margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; min-height: 100px; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; }
        .no-claims { text-align: center; padding: 30px; color: var(--text-light); }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="logo">Lost & Found</div>
        <div class="nav-menu">
            <a href="home.php" class="nav-item"><i class="fas fa-home"></i> Home</a>
            <a href="my_reports.php" class="nav-item"><i class="fas fa-list"></i> My Reports</a>
            <a href="notifications.php" class="nav-item">
                <i class="fas fa-bell"></i> Notifications
                <?php if (isset($notifications) && $notifications > 0): ?>
                    <span class="notification-badge"><?php echo $notifications; ?></span>
                <?php endif; ?>
            </a>
            <a href="profile.php" class="nav-item"><i class="fas fa-user"></i> Profile</a>
            <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="container">
        <h1>My Claims</h1>
        
        <?php if ($success_message): ?>
            <div class="success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <div class="tab active" data-tab="my-claims">Claims I've Made</div>
            <div class="tab" data-tab="received-claims">Claims I've Received</div>
        </div>
        
        <div id="my-claims" class="tab-content active">
            <?php if ($my_claims && $my_claims->num_rows > 0): ?>
                <?php while($claim = $my_claims->fetch_assoc()): ?>
                    <div class="claim-card">
                        <div class="claim-header">
                            <div class="claim-title"><?php echo htmlspecialchars($claim['Item_Name']); ?></div>
                            <div class="claim-status status-<?php echo $claim['Claim_Status']; ?>">
                                <?php echo ucfirst($claim['Claim_Status']); ?>
                            </div>
                        </div>
                        <div class="claim-body">
                            <?php if (!empty($claim['Image_URL'])): ?>
                                <img src="<?php echo htmlspecialchars($claim['Image_URL']); ?>" alt="Item Image" class="claim-image">
                            <?php else: ?>
                                <div class="claim-image-placeholder">
                                    <i class="fas fa-image fa-3x"></i>
                                </div>
                            <?php endif; ?>
                            <div class="claim-details">
                                <div class="claim-property">
                                    <div class="claim-label">Location Found</div>
                                    <div class="claim-value"><?php echo htmlspecialchars($claim['Location']); ?></div>
                                </div>
                                <div class="claim-property">
                                    <div class="claim-label">Date Found</div>
                                    <div class="claim-value"><?php echo date('F d, Y', strtotime($claim['Date_Lost_Found'])); ?></div>
                                </div>
                                <div class="claim-property">
                                    <div class="claim-label">Reported By</div>
                                    <div class="claim-value"><?php echo htmlspecialchars($claim['Reporter_Name']); ?></div>
                                </div>
                                <?php if (!empty($claim['Reporter_Contact'])): ?>
                                <div class="claim-property">
                                    <div class="claim-label">Contact</div>
                                    <div class="claim-value"><?php echo htmlspecialchars($claim['Reporter_Contact']); ?></div>
                                </div>
                                <?php endif; ?>
                                <div class="claim-property">
                                    <div class="claim-label">Claim Date</div>
                                    <div class="claim-value"><?php echo date('F d, Y', strtotime($claim['Created_At'])); ?></div>
                                </div>
                                <div class="claim-description">
                                    <div class="claim-label">Your Claim Description</div>
                                    <div class="claim-value"><?php echo nl2br(htmlspecialchars($claim['Claim_Description'])); ?></div>
                                </div>
                                <?php if (!empty($claim['Admin_Notes']) && ($claim['Claim_Status'] === 'approved' || $claim['Claim_Status'] === 'rejected')): ?>
                                <div class="claim-description" style="margin-top: 10px; background: <?php echo $claim['Claim_Status'] === 'approved' ? '#e8f5e9' : '#ffebee'; ?>">
                                    <div class="claim-label">Response from Reporter</div>
                                    <div class="claim-value"><?php echo nl2br(htmlspecialchars($claim['Admin_Notes'])); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="claim-actions">
                            <a href="view_item.php?id=<?php echo $claim['Item_ID']; ?>" class="btn btn-primary">
                                <i class="fas fa-eye"></i> View Item
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-claims">
                    <i class="fas fa-info-circle fa-3x" style="color: var(--primary-color); margin-bottom: 15px;"></i>
                    <p>You haven't made any claims yet.</p>
                    <p style="margin-top: 10px; font-size: 0.9rem;">When you find an item that belongs to you in the found items list, you can submit a claim.</p>
                    <a href="search.php?type=found" class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-search"></i> Browse Found Items
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <div id="received-claims" class="tab-content">
            <?php if ($received_claims && $received_claims->num_rows > 0): ?>
                <?php while($claim = $received_claims->fetch_assoc()): ?>
                    <div class="claim-card">
                        <div class="claim-header">
                            <div class="claim-title"><?php echo htmlspecialchars($claim['Item_Name']); ?></div>
                            <div class="claim-status status-<?php echo $claim['Claim_Status']; ?>">
                                <?php echo ucfirst($claim['Claim_Status']); ?>
                            </div>
                        </div>
                        <div class="claim-body">
                            <?php if (!empty($claim['Image_URL'])): ?>
                                <img src="<?php echo htmlspecialchars($claim['Image_URL']); ?>" alt="Item Image" class="claim-image">
                            <?php else: ?>
                                <div class="claim-image-placeholder">
                                    <i class="fas fa-image fa-3x"></i>
                                </div>
                            <?php endif; ?>
                            <div class="claim-details">
                                <div class="claim-property">
                                    <div class="claim-label">Location Found</div>
                                    <div class="claim-value"><?php echo htmlspecialchars($claim['Location']); ?></div>
                                </div>
                                <div class="claim-property">
                                    <div class="claim-label">Date Found</div>
                                    <div class="claim-value"><?php echo date('F d, Y', strtotime($claim['Date_Lost_Found'])); ?></div>
                                </div>
                                <div class="claim-property">
                                    <div class="claim-label">Claimed By</div>
                                    <div class="claim-value"><?php echo htmlspecialchars($claim['Claimant_Name']); ?></div>
                                </div>
                                <?php if (!empty($claim['Claimant_Contact'])): ?>
                                <div class="claim-property">
                                    <div class="claim-label">Contact</div>
                                    <div class="claim-value"><?php echo htmlspecialchars($claim['Claimant_Contact']); ?></div>
                                </div>
                                <?php endif; ?>
                                <div class="claim-property">
                                    <div class="claim-label">Claim Date</div>
                                    <div class="claim-value"><?php echo date('F d, Y', strtotime($claim['Created_At'])); ?></div>
                                </div>
                                <div class="claim-description">
                                    <div class="claim-label">Claim Description</div>
                                    <div class="claim-value"><?php echo nl2br(htmlspecialchars($claim['Claim_Description'])); ?></div>
                                </div>
                                
                                <?php if (!empty($claim['Verification_Answers'])): ?>
                                <div class="claim-description" style="margin-top: 10px; background: #e3f2fd;">
                                    <div class="claim-label">Verification Answers</div>
                                    <?php 
                                    $verification = json_decode($claim['Verification_Answers'], true);
                                    if ($verification): 
                                    ?>
                                    <div class="claim-property" style="margin-top: 10px;">
                                        <div class="claim-label">Item Color</div>
                                        <div class="claim-value"><?php echo htmlspecialchars($verification['color'] ?? 'Not provided'); ?></div>
                                    </div>
                                    <?php if (!empty($verification['brand'])): ?>
                                    <div class="claim-property" style="margin-top: 10px;">
                                        <div class="claim-label">Item Brand</div>
                                        <div class="claim-value"><?php echo htmlspecialchars($verification['brand']); ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <div class="claim-property" style="margin-top: 10px;">
                                        <div class="claim-label">Distinguishing Features</div>
                                        <div class="claim-value"><?php echo htmlspecialchars($verification['distinguishing_features'] ?? 'Not provided'); ?></div>
                                    </div>
                                    <?php if (!empty($verification['approximate_value'])): ?>
                                    <div class="claim-property" style="margin-top: 10px;">
                                        <div class="claim-label">Approximate Value</div>
                                        <div class="claim-value"><?php echo htmlspecialchars($verification['approximate_value']); ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($verification['date_lost'])): ?>
                                    <div class="claim-property" style="margin-top: 10px;">
                                        <div class="claim-label">Date Lost</div>
                                        <div class="claim-value"><?php echo htmlspecialchars($verification['date_lost']); ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($claim['Admin_Notes']) && ($claim['Claim_Status'] === 'approved' || $claim['Claim_Status'] === 'rejected')): ?>
                                <div class="claim-description" style="margin-top: 10px; background: <?php echo $claim['Claim_Status'] === 'approved' ? '#e8f5e9' : '#ffebee'; ?>">
                                    <div class="claim-label">Your Response</div>
                                    <div class="claim-value"><?php echo nl2br(htmlspecialchars($claim['Admin_Notes'])); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="claim-actions">
                            <a href="view_item.php?id=<?php echo $claim['Item_ID']; ?>" class="btn btn-primary">
                                <i class="fas fa-eye"></i> View Item
                            </a>
                            <?php if ($claim['Claim_Status'] === 'pending'): ?>
                                <button class="btn btn-success" onclick="openApproveModal(<?php echo $claim['Claim_ID']; ?>)">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button class="btn btn-danger" onclick="openRejectModal(<?php echo $claim['Claim_ID']; ?>)">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-claims">
                    <i class="fas fa-info-circle fa-3x" style="color: var(--primary-color); margin-bottom: 15px;"></i>
                    <p>You haven't received any claims for your found items yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Approve Modal -->
    <div id="approve-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Approve Claim</div>
                <span class="close-modal" onclick="closeModal('approve-modal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="claim_id" id="approve-claim-id">
                <div class="modal-body">
                    <p>Are you sure you want to approve this claim? This will mark the item as claimed.</p>
                    <div class="form-group">
                        <label for="approve-notes">Notes (optional)</label>
                        <textarea id="approve-notes" name="admin_notes" placeholder="Add any additional information or instructions for the claimant"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('approve-modal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve Claim</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Reject Modal -->
    <div id="reject-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Reject Claim</div>
                <span class="close-modal" onclick="closeModal('reject-modal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="claim_id" id="reject-claim-id">
                <div class="modal-body">
                    <p>Are you sure you want to reject this claim?</p>
                    <div class="form-group">
                        <label for="reject-notes">Reason for rejection</label>
                        <textarea id="reject-notes" name="admin_notes" placeholder="Please provide a reason for rejecting this claim" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('reject-modal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Claim</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Tab switching
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                // Remove active class from all tabs and content
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                // Add active class to clicked tab and corresponding content
                tab.classList.add('active');
                document.getElementById(tab.dataset.tab).classList.add('active');
            });
        });
        
        // Modal functions
        function openApproveModal(claimId) {
            document.getElementById('approve-claim-id').value = claimId;
            document.getElementById('approve-modal').style.display = 'block';
        }
        
        function openRejectModal(claimId) {
            document.getElementById('reject-claim-id').value = claimId;
            document.getElementById('reject-modal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>