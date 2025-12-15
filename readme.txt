=== France Relocation Member Tools ===
Contributors: relo2france
Tags: france, relocation, visa, membership, documents
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Premium member features for the France Relocation Assistant - document generation, checklists, guides, and personalized relocation planning.

== Description ==

France Relocation Member Tools is an add-on plugin that extends the France Relocation Assistant with premium features for members.

**Features:**

* **Personalized Dashboard** - Welcome message, progress stats, recommended next steps
* **Member Profile** - AI-guided onboarding, profile management, document status tracking
* **Document Generation** - Create personalized visa documents through conversational chat
* **Interactive Checklists** - Track your visa application progress
* **Glossary** - Comprehensive glossary of French relocation terms
* **Guides** - Personalized guides for apostilles, pet relocation, mortgages
* **Upload & Verify** - Track your document collection progress
* **Lead Time Planning** - Tasks ordered by how long they take

**Document Types:**

* Visa Cover Letter
* Proof of Sufficient Means (Financial Statement)
* No Work Attestation (Attestation sur l'honneur)
* Proof of Accommodation Letter

**Requirements:**

* France Relocation Assistant (main plugin)
* MemberPress (recommended) or compatible membership plugin
* PHP 7.4+
* WordPress 5.8+

== Installation ==

1. Ensure the France Relocation Assistant plugin is installed and activated
2. Install and configure MemberPress (recommended) for membership management
3. Upload the `france-relocation-member-tools` folder to `/wp-content/plugins/`
4. Activate the plugin through the 'Plugins' menu in WordPress
5. Configure membership levels in MemberPress

== Frequently Asked Questions ==

= What membership plugin do I need? =

We recommend MemberPress for the best experience. The plugin also supports Paid Memberships Pro, Restrict Content Pro, and WooCommerce Memberships.

= Can I test without a membership plugin? =

Yes! Enable "Demo Mode" in settings to test member features without a membership plugin.

= Are documents generated in French or English? =

Documents are generated based on application location. US applications generate English documents; renewals from France generate French documents.

== Changelog ==

= 1.0.9 =
* Fixed: Navigation functions now correctly use FRAMemberTools.navigateToSection
* Fixed: Glossary accordions now expand/collapse properly
* Fixed: Dashboard action buttons now navigate correctly
* Improved: Glossary terms with full content now display inline (removed non-functional Learn More button)
* New: Upload & Verify feature with full implementation
* New: Document tracking for 7 common visa documents
* Added: Full term details for Apostille displayed inline
* Added: File upload tracking and "I Have This" marking

= 1.0.8 =
* Fixed: Profile and My Documents render functions

= 1.0.0 =
* Initial release
* Dashboard with progress tracking
* Profile management with AI onboarding
* Document generation (4 document types)
* Interactive checklists
* Glossary with French relocation terms
* Guides for apostilles, pets, mortgages
* MemberPress integration

== Upgrade Notice ==

= 1.0.9 =
Important bug fixes for navigation and glossary functionality. Upload & Verify feature now fully implemented.
