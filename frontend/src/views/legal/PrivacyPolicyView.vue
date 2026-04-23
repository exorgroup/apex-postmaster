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
        <h1>Privacy Policy</h1>
        <p class="lastUpdated">Last Updated: {{ new Date().toLocaleDateString('en-GB') }}</p>

        <div class="legalContent">
          <h2>1. Introduction</h2>
          <p>This Privacy Policy explains how APEX PostMaster ("we", "us", or "our"), a company registered in Malta, collects, uses, processes, and protects your personal data in accordance with the General Data Protection Regulation (GDPR) (EU) 2016/679 and the Data Protection Act (Chapter 586 of the Laws of Malta).</p>

          <h2>2. Data Controller</h2>
          <p>APEX PostMaster is the data controller responsible for your personal data. For any privacy-related inquiries, please contact us at privacy@apexpostmaster.com.</p>

          <h2>3. Personal Data We Collect</h2>
          <p>We collect and process the following categories of personal data:</p>
          <ul>
            <li><strong>Account Information:</strong> Name, email address, password (encrypted), phone number</li>
            <li><strong>Profile Information:</strong> Company name, timezone, language preferences, profile picture</li>
            <li><strong>Social Media Accounts:</strong> OAuth tokens and connection details for connected platforms (YouTube, Facebook, Instagram)</li>
            <li><strong>Usage Data:</strong> Posts created, analytics data, campaign information, AI tool usage</li>
            <li><strong>Payment Information:</strong> Billing details processed through Stripe (we do not store full credit card numbers)</li>
            <li><strong>Technical Data:</strong> IP address, browser type, device information, cookies</li>
          </ul>

          <h2>4. Legal Basis for Processing</h2>
          <p>We process your personal data under the following legal bases:</p>
          <ul>
            <li><strong>Contract Performance:</strong> To provide our services as per our Terms of Service</li>
            <li><strong>Legitimate Interests:</strong> To improve our services, prevent fraud, and ensure security</li>
            <li><strong>Consent:</strong> For marketing communications and non-essential cookies</li>
            <li><strong>Legal Obligation:</strong> To comply with applicable laws and regulations</li>
          </ul>

          <h2>5. How We Use Your Data</h2>
          <p>We use your personal data to:</p>
          <ul>
            <li>Provide and maintain our social media management services</li>
            <li>Process payments and manage subscriptions</li>
            <li>Send service-related communications and notifications</li>
            <li>Improve and personalize our services</li>
            <li>Provide customer support</li>
            <li>Comply with legal obligations and prevent fraud</li>
            <li>Send marketing communications (with your consent)</li>
          </ul>

          <h2>6. Data Sharing and Disclosure</h2>
          <p>We may share your data with:</p>
          <ul>
            <li><strong>Service Providers:</strong> Cloud hosting (AWS/DigitalOcean), payment processing (Stripe), AI services (Anthropic)</li>
            <li><strong>Social Media Platforms:</strong> When you authorize us to post on your behalf</li>
            <li><strong>Legal Authorities:</strong> When required by law or to protect our rights</li>
          </ul>
          <p>We do not sell your personal data to third parties.</p>

          <h2>7. International Data Transfers</h2>
          <p>Your data may be transferred to and processed in countries outside the European Economic Area (EEA). We ensure appropriate safeguards are in place, including Standard Contractual Clauses approved by the European Commission.</p>

          <h2>8. Data Retention</h2>
          <p>We retain your personal data for as long as your account is active or as needed to provide services. After account deletion, we may retain certain data for up to 7 years to comply with legal obligations, resolve disputes, and enforce our agreements.</p>

          <h2>9. Your Rights Under GDPR</h2>
          <p>You have the following rights:</p>
          <ul>
            <li><strong>Right of Access:</strong> Request a copy of your personal data</li>
            <li><strong>Right to Rectification:</strong> Correct inaccurate or incomplete data</li>
            <li><strong>Right to Erasure:</strong> Request deletion of your data ("right to be forgotten")</li>
            <li><strong>Right to Restrict Processing:</strong> Limit how we use your data</li>
            <li><strong>Right to Data Portability:</strong> Receive your data in a structured format</li>
            <li><strong>Right to Object:</strong> Object to processing based on legitimate interests</li>
            <li><strong>Right to Withdraw Consent:</strong> Withdraw consent at any time</li>
            <li><strong>Right to Lodge a Complaint:</strong> File a complaint with the Office of the Information and Data Protection Commissioner (Malta)</li>
          </ul>

          <h2>10. Security Measures</h2>
          <p>We implement appropriate technical and organizational measures to protect your data, including encryption, access controls, regular security assessments, and staff training.</p>

          <h2>11. Cookies</h2>
          <p>We use cookies and similar technologies. For detailed information, please see our Cookie Policy.</p>

          <h2>12. Children's Privacy</h2>
          <p>Our services are not intended for individuals under 16 years of age. We do not knowingly collect data from children.</p>

          <h2>13. Changes to This Policy</h2>
          <p>We may update this Privacy Policy periodically. We will notify you of material changes via email or through our platform.</p>

          <h2>14. Contact Information</h2>
          <p>For privacy-related inquiries or to exercise your rights, contact us at:</p>
          <p>Email: privacy@apexpostmaster.com<br>
          Address: [Your Malta Address]</p>

          <p><strong>Supervisory Authority:</strong><br>
          Office of the Information and Data Protection Commissioner<br>
          Level 2, Airways House, High Street, Sliema SLM 1549, Malta<br>
          Phone: +356 2328 7100<br>
          Website: idpc.org.mt</p>
        </div>
      </div>
    </section>

    <footer class="footer">
      <p>&copy; {{ new Date().getFullYear() }} APEX PostMaster. All rights reserved.</p>
    </footer>
  </div>
</template>

<style scoped src="@/assets/legal.css"></style>
