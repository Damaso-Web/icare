<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// B637 (revised) + B639: the Dean/Dept Chair dashboard header shows the
// account's own college abbreviation (e.g. "CIS") in place of the fixed
// "OSS" label. Covers the dashboard API's affiliation_abbrev field for
// both roles, the fallback when no college can be resolved, and that it's
// absent for roles the bugs didn't mention.
class DashboardAffiliationAbbrevTest extends TestCase
{
    use RefreshDatabase;

    public function test_dean_dashboard_includes_their_college_abbrev_from_the_college_record(): void
    {
        College::create(['name' => 'College of Information Sciences (CIS)', 'abbrev' => 'CIS']);
        $dean = User::factory()->create(['role' => 'dean', 'college' => 'College of Information Sciences (CIS)']);
        Sanctum::actingAs($dean);

        $dash = $this->getJson('/api/dashboard')->assertOk()->json();
        $this->assertSame('CIS', $dash['affiliation_abbrev']);
    }

    public function test_dept_chair_dashboard_includes_their_college_abbrev(): void
    {
        College::create(['name' => 'College of Business (CB)', 'abbrev' => 'CB']);
        $chair = User::factory()->create(['role' => 'dept_chair', 'college' => 'College of Business (CB)']);
        Sanctum::actingAs($chair);

        $dash = $this->getJson('/api/dashboard')->assertOk()->json();
        $this->assertSame('CB', $dash['affiliation_abbrev']);
    }

    public function test_abbrev_falls_back_to_the_code_in_parentheses_when_no_college_record_matches(): void
    {
        // No College row seeded at all - abbrev() still pulls "CE" out of
        // the saved college string itself.
        $dean = User::factory()->create(['role' => 'dean', 'college' => 'College of Engineering (CE)']);
        Sanctum::actingAs($dean);

        $dash = $this->getJson('/api/dashboard')->assertOk()->json();
        $this->assertSame('CE', $dash['affiliation_abbrev']);
    }

    public function test_abbrev_is_null_when_the_college_has_no_resolvable_code(): void
    {
        $dean = User::factory()->create(['role' => 'dean', 'college' => 'Some Unlisted College']);
        Sanctum::actingAs($dean);

        $dash = $this->getJson('/api/dashboard')->assertOk()->json();
        $this->assertNull($dash['affiliation_abbrev']);
    }

    public function test_plain_faculty_dashboard_also_carries_the_field_but_it_is_role_gated_on_the_frontend(): void
    {
        College::create(['name' => 'College of Information Sciences (CIS)', 'abbrev' => 'CIS']);
        $faculty = User::factory()->create(['role' => 'faculty', 'college' => 'College of Information Sciences (CIS)', 'department' => 'Information Technology']);
        Sanctum::actingAs($faculty);

        // facultyDashboard() is shared by Dept Chair and plain Faculty, so
        // the field is present either way - the Vue layer is what keeps
        // plain Faculty showing "OSS" (see showsCollegeAbbrev in Dashboard.vue).
        $dash = $this->getJson('/api/dashboard')->assertOk()->json();
        $this->assertSame('CIS', $dash['affiliation_abbrev']);
    }
}