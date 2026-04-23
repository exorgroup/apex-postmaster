<script setup>
import { useRouter } from 'vue-router'
import Button from 'primevue/button'

const router = useRouter()
const goBack = () => router.push('/')
</script>

<template>
  <div class="legalPage">
    <nav class="navbar">
      <div class="navContainer">
        <div class="navBrand" @click="goBack" style="cursor: pointer">
          <span class="brandIcon">▲</span>
          <span class="brandText">APEX PostMaster</span>
        </div>
        <Button label="Back to Home" text @click="goBack" />
      </div>
    </nav>

    <section class="contentSection">
      <div class="sectionContainer">
        <h1>Cookie Policy</h1>
        <p class="lastUpdated">Last Updated: {{ new Date().toLocaleDateString('en-GB') }}</p>

        <div class="legalContent">
          <h2>1. What Are Cookies?</h2>
          <p>Cookies are small text files stored on your device when you visit our website. They help us provide you with a better experience by remembering your preferences and analyzing how you use our services.</p>

          <h2>2. Types of Cookies We Use</h2>

          <h3>2.1 Strictly Necessary Cookies</h3>
          <p>These cookies are essential for our website to function properly. They cannot be disabled.</p>
          <ul>
            <li><strong>Session Cookies:</strong> Maintain your logged-in state</li>
            <li><strong>Security Cookies:</strong> Detect authentication abuse and protect your account</li>
            <li><strong>CSRF Tokens:</strong> Prevent cross-site request forgery attacks</li>
          </ul>

          <h3>2.2 Functional Cookies</h3>
          <p>These cookies enable enhanced functionality and personalization. You can disable them, but some features may not work properly.</p>
          <ul>
            <li><strong>Preferences:</strong> Remember your language, timezone, and theme settings</li>
            <li><strong>UI State:</strong> Remember collapsed/expanded panels and layout preferences</li>
          </ul>

          <h3>2.3 Analytics Cookies</h3>
          <p>These cookies help us understand how visitors use our website. All data is aggregated and anonymous.</p>
          <ul>
            <li><strong>Usage Analytics:</strong> Track page views, session duration, and feature usage</li>
            <li><strong>Performance Monitoring:</strong> Identify technical issues and slow pages</li>
          </ul>

          <h3>2.4 Marketing Cookies (Requiring Consent)</h3>
          <p>These cookies track your activity across websites to provide personalized advertising. You can opt out at any time.</p>
          <ul>
            <li><strong>Advertising:</strong> Show relevant ads based on your interests</li>
            <li><strong>Social Media:</strong> Enable sharing features and track social interactions</li>
          </ul>

          <h2>3. Third-Party Cookies</h2>
          <p>We use services from trusted third parties who may set their own cookies:</p>
          <ul>
            <li><strong>Google Analytics:</strong> Website traffic analysis</li>
            <li><strong>Stripe:</strong> Payment processing</li>
            <li><strong>Social Media Platforms:</strong> OAuth authentication and sharing</li>
          </ul>

          <h2>4. Cookie Duration</h2>
          <ul>
            <li><strong>Session Cookies:</strong> Deleted when you close your browser</li>
            <li><strong>Persistent Cookies:</strong> Remain for a specified period (typically 30-365 days)</li>
          </ul>

          <h2>5. How to Control Cookies</h2>

          <h3>5.1 Through Our Cookie Banner</h3>
          <p>When you first visit our website, you can accept or reject non-essential cookies through our cookie consent banner. You can change your preferences at any time by clicking "Cookie Settings" in the footer.</p>

          <h3>5.2 Through Your Browser</h3>
          <p>Most browsers allow you to refuse cookies or delete them. Methods vary by browser:</p>
          <ul>
            <li><strong>Chrome:</strong> Settings > Privacy and Security > Cookies</li>
            <li><strong>Firefox:</strong> Options > Privacy & Security > Cookies and Site Data</li>
            <li><strong>Safari:</strong> Preferences > Privacy > Cookies and website data</li>
            <li><strong>Edge:</strong> Settings > Cookies and site permissions</li>
          </ul>

          <h3>5.3 Opt-Out Links</h3>
          <ul>
            <li><strong>Google Analytics:</strong> <a href="https://tools.google.com/dlpage/gaoptout" target="_blank">Browser add-on</a></li>
            <li><strong>NAI Opt-Out:</strong> <a href="http://optout.networkadvertising.org/" target="_blank">Network Advertising Initiative</a></li>
            <li><strong>DAA Opt-Out:</strong> <a href="http://optout.aboutads.info/" target="_blank">Digital Advertising Alliance</a></li>
          </ul>

          <h2>6. Impact of Disabling Cookies</h2>
          <p>If you disable certain cookies, some features may not function properly:</p>
          <ul>
            <li>You may need to log in each time you visit</li>
            <li>Your preferences may not be saved</li>
            <li>Some personalization features may not work</li>
          </ul>

          <h2>7. Do Not Track Signals</h2>
          <p>Some browsers transmit "Do Not Track" signals. Currently, there is no industry standard for responding to these signals. We do not currently respond to Do Not Track signals.</p>

          <h2>8. Updates to This Policy</h2>
          <p>We may update this Cookie Policy from time to time. Changes will be posted on this page with an updated "Last Updated" date. Continued use of our services after changes constitutes acceptance.</p>

          <h2>9. Contact Us</h2>
          <p>For questions about our use of cookies, contact us at:<br>
          Email: privacy@apexpostmaster.com</p>
        </div>
      </div>
    </section>

    <footer class="footer">
      <p>&copy; {{ new Date().getFullYear() }} APEX PostMaster. All rights reserved.</p>
    </footer>
  </div>
</template>

<style scoped src="@/assets/legal.css"></style>
<style scoped>
.legalContent h3 {
  font-size: 1.125rem;
  font-weight: 600;
  margin-top: 1.5rem;
  margin-bottom: 0.75rem;
  color: var(--landing-text);
}

.legalContent a {
  color: var(--landing-accent);
  text-decoration: underline;
}

.legalContent a:hover {
  opacity: 0.8;
}
</style>
