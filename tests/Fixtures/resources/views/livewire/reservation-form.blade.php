<form wire:submit="submit" data-testid="reservation-form">
    <input wire:model="name" name="name" />
    @error('name')
        <p data-testid="name-error">{{ $message }}</p>
    @enderror
    <button type="submit">Submit</button>
    @if ($submitted)
        <p data-testid="submitted">Submitted {{ $placement }}</p>
    @endif
</form>
