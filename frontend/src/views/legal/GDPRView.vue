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
        <h1>GDPR Compliance</h1>
        <p class="lastUpdated">Last Updated: {{ new Date().toLocaleDateString('en-GB') }}</p>

        <div class="legalContent">
          <h2>1. Our Commitment to GDPR</h2>
          <p>APEX PostMaster is committed to protecting your personal data and respecting your privacy rights under the General Data Protection Regulation (GDPR) (EU) 2016/679. As a Malta-registered company, we comply with both EU and Maltese data protection laws.</p>

          <h2>2. Your Rights Under GDPR</h2>
          <p>You have the following rights regarding your personal data:</p>

          <h3>2.1 Right of Access (Article 15)</h3>
          <p>You have the right to obtain confirmation as to whether we process your personal data and to access that data. To request your data, contact us at privacy@apexpostmaster.com.</p>

          <h3>2.2 Right to Rectification (Article 16)</h3>
          <p>You can correct inaccurate personal data directly in your account settings or by contacting us.</p>

          <h3>2.3 Right to Erasure / "Right to be Forgotten" (Article 17)</h3>
          <p>You can request deletion of your personal data when:</p>
          <ul>
            <li>The data is no longer necessary for the purposes it was collected</li>
            <li>You withdraw consent and there is no other legal basis for processing</li>
            <li>You object to processing and there are no overriding legitimate grounds</li>
            <li>The data was unlawfully processed</li>
          </ul>
          <p>To request deletion, go to Account Settings > Delete Account or contact us.</p>

          <h3>2.4 Right to Restriction of Processing (Article 18)</h3>
          <p>You can request restriction of processing when:</p>
          <ul>
            <li>You contest the accuracy of personal data</li>
            <li>Processing is unlawful but you oppose erasure</li>
            <li>We no longer need the data but you require it for legal claims</li>
            <li>You object to processing pending verification of legitimate grounds</li>
          </ul>

          <h3>2.5 Right to Data Portability (Article 20)</h3>
          <p>You can receive your personal data in a structured, commonly used, machine-readable format. You can request data export from Account Settings > Export Data.</p>

          <h3>2.6 Right to Object (Article 21)</h3>
          <p>You have the right to object to processing based on legitimate interests or for direct marketing purposes. To opt out of marketing emails, click "Unsubscribe" in any email or update preferences in your account.</p>

          <h3>2.7 Right to Withdraw Consent (Article 7)</h3>
          <p>Where processing is based on consent, you can withdraw it at any time without affecting the lawfulness of processing before withdrawal.</p>

          <h3>2.8 Right Not to Be Subject to Automated Decision-Making (Article 22)</h3>
          <p>We do not make decisions based solely on automated processing that produce legal or similarly significant effects.</p>

          <h2>3. How to Exercise Your Rights</h2>
          <p>To exercise any of these rights:</p>
          <ul>
            <li>Email: privacy@apexpostmaster.com</li>
            <li>In-app: Account Settings > Privacy & Data Rights</li>
            <li>Post: [Your Malta Address]</li>
          </ul>
          <p>We will respond to verified requests within one month. This may be extended to two months for complex requests.</p>

          <h2>4. Data Processing Information</h2>

          <h3>4.1 Data Controller</h3>
          <p>APEX PostMaster<br>
          [Your Malta Address]<br>
          Email: privacy@apexpostmaster.com</p>

          <h3>4.2 Legal Basis for Processing</h3>
          <p>We process your data under the following legal bases:</p>
          <ul>
            <li><strong>Contract (Article 6(1)(b)):</strong> To perform our services</li>
            <li><strong>Legitimate Interests (Article 6(1)(f)):</strong> To improve services, prevent fraud</li>
            <li><strong>Consent (Article 6(1)(a)):</strong> For marketing and non-essential cookies</li>
            <li><strong>Legal Obligation (Article 6(1)(c)):</strong> To comply with laws</li>
          </ul>

          <h3>4.3 Data Recipients</h3>
          <p>We share data with:</p>
          <ul>
            <li>Cloud service providers (AWS/DigitalOcean)</li>
            <li>Payment processors (Stripe)</li>
            <li>AI service providers (Anthropic)</li>
            <li>Social media platforms (when you connect accounts)</li>
          </ul>

          <h3>4.4 International Transfers</h3>
          <p>Data may be transferred outside the EEA. We ensure appropriate safeguards through:</p>
          <ul>
            <li>Standard Contractual Clauses (SCCs)</li>
            <li>Adequacy decisions by the European Commission</li>
            <li>Processor binding corporate rules</li>
          </ul>

          <h3>4.5 Retention Periods</h3>
          <ul>
            <li><strong>Account Data:</strong> Duration of account + 30 days</li>
            <li><strong>Transaction Records:</strong> 7 years (legal requirement)</li>
            <li><strong>Marketing Data:</strong> Until consent withdrawn + 30 days</li>
            <li><strong>Analytics Data:</strong> 26 months</li>
          </ul>

          <h2>5. Data Protection Officer</h2>
          <p>For data protection inquiries, contact our Data Protection Officer:<br>
          Email: dpo@apexpostmaster.com</p>

          <h2>6. Supervisory Authority</h2>
          <p>You have the right to lodge a complaint with the supervisory authority:</p>
          <p><strong>Office of the Information and Data Protection Commissioner (Malta)</strong><br>
          Level 2, Airways House<br>
          High Street, Sliema SLM 1549, Malta<br>
          Phone: +356 2328 7100<br>
          Email: idpc.info.mt@idpc.org.mt<br>
          Website: idpc.org.mt</p>

          <h2>7. Data Breaches</h2>
          <p>In the event of a data breach likely to result in high risk to your rights, we will notify you without undue delay (within 72 hours where feasible) and the supervisory authority as required by Article 33.</p>

          <h2>8. Children's Data</h2>
          <p>Our services are not directed to individuals under 16. We do not knowingly collect data from children. If you believe we have collected data from a child, contact us immediately.</p>

          <h2>9. Updates</h2>
          <p>This GDPR information page will be updated to reflect any changes in our data processing practices or legal requirements.</p>
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
</style>
