<?php
// Donor Registration Form Component
function renderDonorRegistrationForm() {
  ?>
  <form class="space-y-6" method="post" action="process_donor_registration.php">
    <div class="space-y-4">
      <h3 class="text-lg font-medium">Personal Information</h3>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="space-y-2">
          <label for="firstName" class="block text-sm font-medium text-gray-700">First Name</label>
          <input type="text" id="firstName" name="firstName" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500" placeholder="Enter your first name" required>
        </div>

        <div class="space-y-2">
          <label for="lastName" class="block text-sm font-medium text-gray-700">Last Name</label>
          <input type="text" id="lastName" name="lastName" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500" placeholder="Enter your last name" required>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="space-y-2">
          <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
          <input type="email" id="email" name="email" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500" placeholder="Enter your email" required>
        </div>

        <div class="space-y-2">
          <label for="phone" class="block text-sm font-medium text-gray-700">Phone Number</label>
          <input type="tel" id="phone" name="phone" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500" placeholder="Enter your phone number" required>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="space-y-2">
          <label for="dob" class="block text-sm font-medium text-gray-700">Date of Birth</label>
          <input type="date" id="dob" name="dob" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500" required>
        </div>

        <fieldset class="space-y-2">
          <legend class="block text-sm font-medium text-gray-700">Gender</legend>
          <div class="flex space-x-4 mt-1">
            <div class="flex items-center">
              <input id="male" name="gender" type="radio" value="male" class="focus:ring-red-500 h-4 w-4 text-red-600 border-gray-300" checked>
              <label for="male" class="ml-2 block text-sm text-gray-700">Male</label>
            </div>
            <div class="flex items-center">
              <input id="female" name="gender" type="radio" value="female" class="focus:ring-red-500 h-4 w-4 text-red-600 border-gray-300">
              <label for="female" class="ml-2 block text-sm text-gray-700">Female</label>
            </div>
            <div class="flex items-center">
              <input id="other" name="gender" type="radio" value="other" class="focus:ring-red-500 h-4 w-4 text-red-600 border-gray-300">
              <label for="other" class="ml-2 block text-sm text-gray-700">Other</label>
            </div>
          </div>
        </fieldset>
      </div>

      <div class="space-y-2">
        <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
        <textarea id="address" name="address" rows="3" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500" placeholder="Enter your full address" required></textarea>
      </div>
    </div>

    <div class="space-y-4">
      <h3 class="text-lg font-medium">Blood Information</h3>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="space-y-2">
          <label for="bloodType" class="block text-sm font-medium text-gray-700">Blood Type</label>
          <select id="bloodType" name="bloodType" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm rounded-md" required>
            <option value="" selected disabled>Select your blood type</option>
            <option value="a_pos">A+</option>
            <option value="a_neg">A-</option>
            <option value="b_pos">B+</option>
            <option value="b_neg">B-</option>
            <option value="ab_pos">AB+</option>
            <option value="ab_neg">AB-</option>
            <option value="o_pos">O+</option>
            <option value="o_neg">O-</option>
            <option value="unknown">I don't know</option>
          </select>
        </div>

        <div class="space-y-2">
          <label for="lastDonation" class="block text-sm font-medium text-gray-700">Last Donation Date (if any)</label>
          <input type="date" id="lastDonation" name="lastDonation" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500">
        </div>
      </div>

      <fieldset class="space-y-2">
        <legend class="block text-sm font-medium text-gray-700">Health Information</legend>
        <div class="space-y-2">
          <div class="flex items-center">
            <input id="healthCondition" name="healthCondition" type="checkbox" class="focus:ring-red-500 h-4 w-4 text-red-600 border-gray-300 rounded" required>
            <label for="healthCondition" class="ml-2 block text-sm text-gray-700">
              I have no known health conditions that would prevent me from donating blood
            </label>
          </div>
          <div class="flex items-center">
            <input id="medications" name="medications" type="checkbox" class="focus:ring-red-500 h-4 w-4 text-red-600 border-gray-300 rounded" required>
            <label for="medications" class="ml-2 block text-sm text-gray-700">
              I am not currently taking any medications that would prevent me from donating blood
            </label>
          </div>
          <div class="flex items-center">
            <input id="recentIllness" name="recentIllness" type="checkbox" class="focus:ring-red-500 h-4 w-4 text-red-600 border-gray-300 rounded" required>
            <label for="recentIllness" class="ml-2 block text-sm text-gray-700">
              I have not had any recent illnesses or infections
            </label>
          </div>
        </div>
      </fieldset>
      <div>
    </div>

    <div class="space-y-4">
      <h3 class="text-lg font-medium">Consent</h3>

      <div class="space-y-2">
        <div class="flex items-center">
          <input id="consent" name="consent" type="checkbox" class="focus:ring-red-500 h-4 w-4 text-red-600 border-gray-300 rounded" required>
          <label for="consent" class="ml-2 block text-sm text-gray-700">
            I consent to donate blood and confirm that all information provided is accurate
          </label>
        </div>
        <div class="flex items-center">
          <input id="contactConsent" name="contactConsent" type="checkbox" class="focus:ring-red-500 h-4 w-4 text-red-600 border-gray-300 rounded">
          <label for="contactConsent" class="ml-2 block text-sm text-gray-700">
            I agree to be contacted for future blood donation drives
          </label>
        </div>
      </div>
    </div>

    <button type="submit" class="w-full py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-700 hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
      Register as Donor
    </button>
  </form>
  <?php
}
?>

