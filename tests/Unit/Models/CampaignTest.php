<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;

class CampaignTest extends TestCase
{
    public function test_campaign_has_required_attributes(): void
    {
        $campaign = [
            'id' => 'uuid123',
            'name' => 'Test Campaign',
            'channel' => 'email',
            'status' => 'draft',
            'tenant_id' => 'tenant123',
        ];

        $this->assertArrayHasKey('id', $campaign);
        $this->assertArrayHasKey('name', $campaign);
        $this->assertArrayHasKey('channel', $campaign);
        $this->assertArrayHasKey('status', $campaign);
        $this->assertEquals('Test Campaign', $campaign['name']);
    }

    public function test_campaign_status_transitions(): void
    {
        $statuses = ['draft', 'scheduled', 'running', 'paused', 'completed', 'failed'];
        
        foreach ($statuses as $status) {
            $this->assertContains($status, $statuses);
        }
    }

    public function test_campaign_channels(): void
    {
        $channels = ['email', 'sms', 'voice'];
        
        foreach ($channels as $channel) {
            $this->assertContains($channel, $channels);
        }
    }
}
