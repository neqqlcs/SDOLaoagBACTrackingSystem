<?php
// Set page-specific variables before including header
$showTitleRight = false;
$isLoginPage = true;
$bodyClass = 'home-bg login-page';
$additionalCssFiles = [
    'assets/css/Login.css'
];
?>

<?php include 'header.php'; ?>

<div class="login-flex-wrapper">
  <div class="login-container">
    <div class="login-box">
        <img src="assets/images/DepEd_Name_Logo.png" alt="DepEd" class="login-logo">
        <!-- Display error messages from PHP -->
        <?php if (isset($error)): ?>
          <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <!-- Form updated to use POST and include name attributes -->
        <form id="loginForm" action="<?php echo url('login.php'); ?>" method="post">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" placeholder="Enter your username" required>

          <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="Enter your password" required>

          <button type="submit">Sign In</button>
        </form>
      </div>
    </div>
    <img src="assets/images/DepEd_Logo.png" alt="DepEd Logo" class="side-logo-login">
  </div>

  <!-- Server-side authentication is used instead of client-side login.js -->