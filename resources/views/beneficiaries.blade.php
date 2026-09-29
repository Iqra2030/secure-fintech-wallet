@extends('layout')
@section('content')
<div class="heading"><a href="/dashboard">← Back to wallet</a><h1>Beneficiaries</h1><p class="muted">Save registered customers you want to send money to.</p></div>
<div class="grid"><section class="card"><h2>Add a beneficiary</h2>
<form method="POST" action="/beneficiaries/lookup">@csrf
<label for="email">Customer email</label><input id="email" name="email" type="email" maxlength="255" value="{{ old('email') }}" placeholder="sara@wallet.test" required>
<button class="full">Find customer</button></form>
@if($candidate)
<hr><h2>Confirm customer</h2><p><strong>{{ $candidate->name }}</strong><br>{{ $candidate->email }}</p>
<form method="POST" action="/beneficiaries">@csrf<input type="hidden" name="beneficiary_user_id" value="{{ $candidate->id }}"><button>Confirm and add beneficiary</button></form>
@endif
</section><section class="card"><h2>Your saved beneficiaries</h2>
@forelse($beneficiaries as $beneficiary)
<div style="padding:16px 0;border-bottom:1px solid #e7ecf2"><strong>{{ $beneficiary->name }}</strong><p>{{ $beneficiary->email }}</p><p class="muted">Added {{ $beneficiary->created_at }} UTC</p>
<form method="POST" action="/beneficiaries/{{ $beneficiary->id }}" onsubmit="return confirm('Remove this beneficiary? Transaction history will stay available.');">@csrf @method('DELETE')<button class="secondary">Remove</button></form></div>
@empty<p class="muted">No beneficiaries yet. Find a customer by email and confirm to add them.</p>@endforelse
</section></div>
@endsection
