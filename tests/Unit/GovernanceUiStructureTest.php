<?php

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Tests\Unit;

defined('_JEXEC') or die;

use PHPUnit\Framework\TestCase;

/**
 * Source-structure regression tests for the Credentials/Dashboard split.
 *
 * A full MVC display() harness (real Joomla application, ACL, DI container,
 * database) is impractical for this admin UI in a unit-test environment, so
 * these tests assert section ownership, section order, and ACL-gate wiring
 * directly against the shipped source: which services each view resolves,
 * which template sections exist in each screen, the relative order those
 * sections render in, and which controller redirects each governance action
 * uses. This is not a substitute for the existing service-level unit tests
 * (GovernanceSetupServiceTest, GovernanceAuditQueryServiceTest, etc.), which
 * already cover business-logic behavior.
 */
class GovernanceUiStructureTest extends TestCase
{
    private const ADMIN_ROOT = __DIR__ . '/../../admin';

    private function credentialsViewSource(): string
    {
        return (string) file_get_contents(self::ADMIN_ROOT . '/src/View/Credentials/HtmlView.php');
    }

    private function credentialsTemplateSource(): string
    {
        return (string) file_get_contents(self::ADMIN_ROOT . '/tmpl/credentials/default.php');
    }

    private function dashboardViewSource(): string
    {
        return (string) file_get_contents(self::ADMIN_ROOT . '/src/View/Dashboard/HtmlView.php');
    }

    private function dashboardTemplateSource(): string
    {
        return (string) file_get_contents(self::ADMIN_ROOT . '/tmpl/dashboard/default.php');
    }

    private function controllerSource(): string
    {
        return (string) file_get_contents(self::ADMIN_ROOT . '/src/Controller/CredentialsController.php');
    }

    public function testCredentialsViewDoesNotResolveGovernanceSetupOrAuditServices(): void
    {
        $source = $this->credentialsViewSource();

        $this->assertStringNotContainsString(
            'GovernanceSetupService',
            $source,
            'Credentials view must not resolve governance setup state; that is Dashboard-owned.'
        );
        $this->assertStringNotContainsString(
            'GovernanceAuditQueryService',
            $source,
            'Credentials view must not resolve the governance audit query service; that is Dashboard-owned.'
        );
        $this->assertStringNotContainsString(
            'GovernanceAuditRetentionService',
            $source,
            'Credentials view must not resolve the governance audit retention service; that is Dashboard-owned.'
        );
    }

    public function testCredentialsViewHasNoGovernanceStatusOrAuditState(): void
    {
        $source = $this->credentialsViewSource();

        $this->assertStringNotContainsString('$governanceStatus', $source);
        $this->assertStringNotContainsString('$auditRows', $source);
        $this->assertStringNotContainsString('$auditFilters', $source);
        $this->assertStringNotContainsString('canViewAudit', $source);
    }

    public function testDashboardViewResolvesGovernanceSetupAndAuditServices(): void
    {
        $source = $this->dashboardViewSource();

        $this->assertStringContainsString('GovernanceSetupService', $source);
        $this->assertStringContainsString('GovernanceAuditQueryService', $source);
    }

    public function testDashboardViewGatesSetupWithCoreAdmin(): void
    {
        $source = $this->dashboardViewSource();

        $this->assertMatchesRegularExpression(
            "/isCoreAdmin\\s*=.*authorise\\('core\\.admin', 'com_mcpserver'\\)/s",
            $source,
            'Dashboard must gate governance setup status/prune visibility behind core.admin.'
        );
    }

    public function testDashboardViewGatesAuditWithManageOrDedicatedAuditAction(): void
    {
        $source = $this->dashboardViewSource();

        $this->assertMatchesRegularExpression(
            "/canViewAudit\\s*=.*mcpserver\\.credential\\.audit.*core\\.manage/s",
            $source,
            'Dashboard must gate audit visibility behind mcpserver.credential.audit or core.manage.'
        );
    }

    public function testDashboardSetupStatusRendersBeforeOperationalCards(): void
    {
        $source = $this->dashboardTemplateSource();

        $setupPos = strpos($source, 'COM_MCPSERVER_GOVERNANCE_SETUP_TITLE');
        $auditPos = strpos($source, 'COM_MCPSERVER_GOVERNANCE_AUDIT_TITLE');

        // 'COM_MCPSERVER_DASHBOARD_CARD_TOTAL' also appears earlier in the
        // template's PHP preamble (the $cards array is built before any HTML
        // renders), so anchor on the cards' actual HTML render container
        // instead of the label constant to reflect true render order.
        $cardsPos = strpos($source, 'row-cols-2 row-cols-md-4 row-cols-xl-7');

        $this->assertIsInt($setupPos, 'Dashboard template must render the governance setup section.');
        $this->assertIsInt($auditPos, 'Dashboard template must render the governance audit section.');
        $this->assertIsInt($cardsPos, 'Dashboard template must render the operational metrics cards container.');

        $this->assertLessThan($cardsPos, $setupPos, 'Governance setup status must render before operational metrics.');
        $this->assertLessThan($cardsPos, $auditPos, 'Governance audit must render before operational metrics.');
    }

    public function testDashboardTemplateContainsPruneControls(): void
    {
        $source = $this->dashboardTemplateSource();

        $this->assertStringContainsString('credentials.prune', $source);
        $this->assertStringContainsString('COM_MCPSERVER_GOVERNANCE_AUDIT_PRUNE_BUTTON', $source);
    }

    public function testCredentialsTemplateDoesNotContainGovernanceSetupOrAuditSections(): void
    {
        $source = $this->credentialsTemplateSource();

        $this->assertStringNotContainsString('COM_MCPSERVER_GOVERNANCE_SETUP_TITLE', $source);
        $this->assertStringNotContainsString('COM_MCPSERVER_GOVERNANCE_AUDIT_TITLE', $source);
        $this->assertStringNotContainsString('credentials.setup', $source);
        $this->assertStringNotContainsString('credentials.prune', $source);
    }

    public function testCredentialsTemplateOrdersWarningThenIssuedTokenThenCreateThenList(): void
    {
        $source = $this->credentialsTemplateSource();

        $warningPos = strpos($source, 'COM_MCPSERVER_CREDENTIALS_NOT_CONFIGURED');
        $issuedPos = strpos($source, 'COM_MCPSERVER_CREDENTIALS_ISSUED_TITLE');
        $createPos = strpos($source, 'COM_MCPSERVER_CREDENTIALS_CREATE_TITLE');
        $listPos = strpos($source, 'COM_MCPSERVER_CREDENTIALS_LIST_TITLE');

        $this->assertIsInt($warningPos);
        $this->assertIsInt($issuedPos);
        $this->assertIsInt($createPos);
        $this->assertIsInt($listPos);

        $this->assertLessThan($issuedPos, $warningPos, 'Warning must render first.');
        $this->assertLessThan($createPos, $issuedPos, 'The one-time issued token must render before the create form.');
        $this->assertLessThan($listPos, $createPos, 'Issue New Credential must render before the credential list.');
    }

    public function testSetupAndPruneControllerActionsRedirectToDashboard(): void
    {
        $source = $this->controllerSource();

        $setupMethod = $this->extractMethodBody($source, 'setup');
        $pruneMethod = $this->extractMethodBody($source, 'prune');

        $this->assertStringContainsString('view=dashboard', $setupMethod);
        $this->assertStringNotContainsString('view=credentials', $setupMethod);

        $this->assertStringContainsString('view=dashboard', $pruneMethod);
        $this->assertStringNotContainsString('view=credentials', $pruneMethod);
    }

    public function testCreateAndRevokeControllerActionsRedirectToCredentials(): void
    {
        $source = $this->controllerSource();

        $createMethod = $this->extractMethodBody($source, 'create');
        $revokeMethod = $this->extractMethodBody($source, 'revoke');

        $this->assertStringContainsString('view=credentials', $createMethod);
        $this->assertStringContainsString('view=credentials', $revokeMethod);
    }

    public function testSetupAndPruneRequireCoreAdmin(): void
    {
        $source = $this->controllerSource();

        $this->assertMatchesRegularExpression(
            "/isAuthorisedForSetupAndTokenValid.*?core\\.admin/s",
            $source,
            'setup()/prune() gate must require core.admin.'
        );
    }

    /**
     * Extracts a public method's body by brace-matching from its declaration,
     * used to scope assertions to one controller action at a time.
     */
    private function extractMethodBody(string $source, string $methodName): string
    {
        $pattern = '/function\s+' . preg_quote($methodName, '/') . '\s*\([^)]*\)\s*:\s*\w+\s*\{/';
        $this->assertMatchesRegularExpression($pattern, $source, "Method {$methodName}() must exist.");

        preg_match($pattern, $source, $matches, PREG_OFFSET_CAPTURE);
        $start = $matches[0][1] + strlen($matches[0][0]);

        $depth = 1;
        $pos = $start;
        $length = strlen($source);

        while ($depth > 0 && $pos < $length) {
            $char = $source[$pos];
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
            }
            $pos++;
        }

        return substr($source, $start, $pos - $start - 1);
    }
}
