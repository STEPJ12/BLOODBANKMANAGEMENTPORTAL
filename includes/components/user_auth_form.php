<?php
// User Authentication Form Component
function renderUserAuthForm($isRegister = false) {
  ?>
  <div class="space-y-4">
    <?php if ($isRegister): ?>
      <div class="space-y-2">
        <label for="role" class="block text-sm font-medium text-gray-700">Select Role</label>
        <select id="role" name="role" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm rounded-md" required>
          <option value="patient" selected>Patient</option>
          <option value="donor">Donor</option>
          <option value="bloodbank">Blood Bank Staff</option>
          <option value="barangay">Barangay Staff</option>
          <option value="admin">Admin</option>
        </select>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div class="space-y-2">
          <label for="firstName" class="block text-sm font-medium text-gray-700">First Name</label>
          <input type="text" id="firstName" name="firstName" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500" placeholder="Enter your first name" required>
        </div>
        <div class="space-y-2">
          <label for="lastName" class="block text-sm font-medium text-gray-700">Last Name</label>
          <input type="text" id="lastName" name="lastName" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500" placeholder="Enter your last name" required>
        </div>
      </div>

      <div class="space-y-2">
        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
        <input type="email" id="email" name="email" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500" placeholder="Enter your email" required>
      </div>
    <?php else: ?>
      <div class="space-y-2">
        <label for="email" class="block text-sm font-medium text-gray-700">Email or Username</label>
        <input type="text" id="email" name="email" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500" placeholder="Enter your email or username" required>
      </div>
    <?php endif; ?>

    <div class="space-y-2">
      <div class="flex items-center justify-between">
        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
        <?php if (!$isRegister): ?>
          <a href="#" class="text-xs text-red-700 hover:underline">Forgot password?</a>
        <?php endif; ?>
      </div>
      <input type="password" id="password" name="password" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500" placeholder="Enter your password" required>
    </div>

    <?php if ($isRegister): ?>
      <div class="space-y-2">
        <label for="confirmPassword" class="block text-sm font-medium text-gray-700">Confirm Password</label>
        <input type="password" id="confirmPassword" name="confirmPassword" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500" placeholder="Confirm your password" required>
      </div>
    <?php endif; ?>

    <button type="submit" class="w-full py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-700 hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
      <?php echo $isRegister ? "Create Account" : "Sign In"; ?>
    </button>

    <?php if (!$isRegister): ?>
      <p class="text-center text-sm text-gray-600">
        Don't have an account?
        <a href="register.php" class="text-red-700 hover:underline">Register</a>
      </p>
    <?php else: ?>
      <p class="text-center text-sm text-gray-600">
        Already have an account?
        <a href="login.php" class="text-red-700 hover:underline">Sign in</a>
      </p>
    <?php endif; ?>
  </div>
  <?php
}
?>
