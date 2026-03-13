<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ContactController
{
    public function index(Request $request, string $tenantId): JsonResponse
    {
        $contacts = DB::table('contacts')
            ->where('tenant_id', $tenantId)
            ->paginate(20);

        return response()->json($contacts);
    }

    public function store(Request $request, string $tenantId): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:320',
            'phone_e164' => 'nullable|string|max:32',
            'external_ref' => 'nullable|string|max:255',
            'attributes' => 'nullable|array',
        ]);

        $contactId = (string) \Illuminate\Support\Str::uuid();

        DB::table('contacts')->insert([
            'id' => $contactId,
            'tenant_id' => $tenantId,
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone_e164' => $validated['phone_e164'] ?? null,
            'external_ref' => $validated['external_ref'] ?? null,
            'attributes' => json_encode($validated['attributes'] ?? []),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['id' => $contactId], 201);
    }

    public function show(Request $request, string $tenantId, string $contactId): JsonResponse
    {
        $contact = DB::table('contacts')
            ->where('id', $contactId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$contact) {
            return response()->json(['error' => 'Contact not found'], 404);
        }

        return response()->json($contact);
    }

    public function update(Request $request, string $tenantId, string $contactId): JsonResponse
    {
        $contact = DB::table('contacts')
            ->where('id', $contactId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$contact) {
            return response()->json(['error' => 'Contact not found'], 404);
        }

        $validated = $request->validate([
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:320',
            'phone_e164' => 'nullable|string|max:32',
            'attributes' => 'nullable|array',
        ]);

        DB::table('contacts')
            ->where('id', $contactId)
            ->update(array_filter(array_merge($validated, ['updated_at' => now()])));

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, string $tenantId, string $contactId): JsonResponse
    {
        $contact = DB::table('contacts')
            ->where('id', $contactId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$contact) {
            return response()->json(['error' => 'Contact not found'], 404);
        }

        DB::table('contacts')->where('id', $contactId)->delete();
        return response()->json(['success' => true]);
    }

    public function import(Request $request, string $tenantId): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getPathname(), 'r');
        $header = fgetcsv($handle);

        $imported = 0;
        while (($data = fgetcsv($handle)) !== false) {
            $contact = array_combine($header, $data);

            DB::table('contacts')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'tenant_id' => $tenantId,
                'first_name' => $contact['first_name'] ?? null,
                'last_name' => $contact['last_name'] ?? null,
                'email' => $contact['email'] ?? null,
                'phone_e164' => $contact['phone_e164'] ?? null,
                'external_ref' => $contact['external_ref'] ?? null,
                'attributes' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $imported++;
        }

        fclose($handle);

        return response()->json(['imported' => $imported], 200);
    }
}
