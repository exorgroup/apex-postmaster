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
        <h1>Terms of Service</h1>
        <p class="lastUpdated">Last Updated: {{ new Date().toLocaleDateString('en-GB') }}</p>

        <div class="legalContent">
          <h2>1. Agreement to Terms</h2>
          <p>These Terms of Service ("Terms") constitute a legally binding agreement between you and APEX PostMaster, a company registered under the laws of Malta ("Company", "we", "us", or "our"). By accessing or using our platform, you agree to be bound by these Terms and our Privacy Policy.</p>

          <h2>2. Eligibility</h2>
          <p>You must be at least 16 years of age to use our services. By using our services, you represent and warrant that you meet this requirement. If you are using our services on behalf of an organization, you represent that you have authority to bind that organization to these Terms.</p>

          <h2>3. Description of Services</h2>
          <p>APEX PostMaster provides social media management services, including but not limited to:</p>
          <ul>
            <li>Scheduling and publishing posts across multiple social media platforms</li>
            <li>AI-powered content generation and optimization</li>
            <li>Analytics and performance tracking</li>
            <li>Campaign planning and management</li>
            <li>Social media listening and monitoring</li>
          </ul>

          <h2>4. Account Registration</h2>
          <p>To use our services, you must create an account. You agree to:</p>
          <ul>
            <li>Provide accurate, current, and complete information</li>
            <li>Maintain the security of your password and account</li>
            <li>Promptly notify us of any unauthorized use or security breach</li>
            <li>Accept responsibility for all activities under your account</li>
          </ul>

          <h2>5. Subscription Plans and Payment</h2>
          <p>Our services are offered under various subscription plans (Free, Pro, Team). You agree to:</p>
          <ul>
            <li>Pay all fees associated with your chosen plan</li>
            <li>Provide valid payment information</li>
            <li>Authorize us to charge your payment method on a recurring basis</li>
          </ul>
          <p>Subscription fees are non-refundable except as required by law or as expressly stated in these Terms. We reserve the right to modify pricing with 30 days' notice.</p>

          <h2>6. Credit System</h2>
          <p>AI-powered features consume credits. Credits are non-transferable and non-refundable. Unused credits expire at the end of each billing cycle unless otherwise specified in your plan.</p>

          <h2>7. Acceptable Use</h2>
          <p>You agree not to:</p>
          <ul>
            <li>Violate any applicable laws or regulations</li>
            <li>Infringe upon intellectual property rights of others</li>
            <li>Post illegal, harmful, threatening, abusive, or defamatory content</li>
            <li>Transmit spam, malware, or viruses</li>
            <li>Attempt to gain unauthorized access to our systems</li>
            <li>Use our services for any automated or bulk posting that violates platform terms</li>
            <li>Reverse engineer or attempt to extract source code from our services</li>
          </ul>

          <h2>8. Social Media Platform Integration</h2>
          <p>Our services integrate with third-party social media platforms. You acknowledge that:</p>
          <ul>
            <li>You must comply with each platform's terms of service</li>
            <li>We are not responsible for changes to third-party APIs</li>
            <li>Platform limitations may affect service availability</li>
            <li>You grant us permission to access and post on your connected accounts</li>
          </ul>

          <h2>9. Intellectual Property</h2>
          <p>All rights, title, and interest in our services, including software, design, and content, are owned by us or our licensors. You retain ownership of content you create, but grant us a license to store, process, and display it as necessary to provide our services.</p>

          <h2>10. Content Moderation</h2>
          <p>We reserve the right to remove content that violates these Terms or applicable laws. We may suspend or terminate accounts that repeatedly violate our policies.</p>

          <h2>11. Service Availability</h2>
          <p>We strive for 99.9% uptime but do not guarantee uninterrupted service. We are not liable for service disruptions caused by:</p>
          <ul>
            <li>Third-party platform outages</li>
            <li>Internet connectivity issues</li>
            <li>Scheduled maintenance</li>
            <li>Force majeure events</li>
          </ul>

          <h2>12. Termination</h2>
          <p>Either party may terminate this agreement:</p>
          <ul>
            <li>You may cancel your subscription at any time from your account settings</li>
            <li>We may suspend or terminate your account for Terms violations</li>
            <li>We may discontinue services with 30 days' notice</li>
          </ul>
          <p>Upon termination, you will lose access to your account and data. We may retain certain data as required by law.</p>

          <h2>13. Warranties and Disclaimers</h2>
          <p>Our services are provided "as is" without warranties of any kind. We disclaim all warranties, express or implied, including warranties of merchantability, fitness for a particular purpose, and non-infringement. We do not warrant that our services will meet your requirements or be error-free.</p>

          <h2>14. Limitation of Liability</h2>
          <p>To the maximum extent permitted by Maltese law, we shall not be liable for:</p>
          <ul>
            <li>Indirect, incidental, special, or consequential damages</li>
            <li>Loss of profits, data, or business opportunities</li>
            <li>Damages exceeding amounts paid to us in the 12 months preceding the claim</li>
          </ul>

          <h2>15. Indemnification</h2>
          <p>You agree to indemnify and hold us harmless from claims arising from your use of our services, violation of these Terms, or infringement of third-party rights.</p>

          <h2>16. Governing Law and Jurisdiction</h2>
          <p>These Terms are governed by the laws of Malta. Any disputes shall be resolved exclusively in the courts of Malta. If you are a consumer in the EU, you may also have rights under your local consumer protection laws.</p>

          <h2>17. Dispute Resolution</h2>
          <p>EU consumers may access the European Commission's Online Dispute Resolution platform at ec.europa.eu/consumers/odr.</p>

          <h2>18. Changes to Terms</h2>
          <p>We may modify these Terms at any time. Material changes will be notified via email or platform notification 30 days before taking effect. Continued use after changes constitutes acceptance.</p>

          <h2>19. Severability</h2>
          <p>If any provision is found unenforceable, the remaining provisions remain in full effect.</p>

          <h2>20. Contact Information</h2>
          <p>For questions about these Terms, contact us at:<br>
          Email: legal@apexpostmaster.com<br>
          Address: [Your Malta Address]</p>
        </div>
      </div>
    </section>

    <footer class="footer">
      <p>&copy; {{ new Date().getFullYear() }} APEX PostMaster. All rights reserved.</p>
    </footer>
  </div>
</template>

<style scoped src="@/assets/legal.css"></style>
