import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import PasswordInput from './PasswordInput';

describe('PasswordInput', () => {
    it('masque la saisie par defaut', () => {
        render(<PasswordInput id="mdp" value="Secret1!" onChange={() => {}} />);

        expect(document.getElementById('mdp')).toHaveAttribute('type', 'password');
        expect(screen.getByRole('button', { name: 'Afficher le mot de passe' })).toHaveAttribute('aria-pressed', 'false');
    });

    it('affiche puis masque la saisie au clic', async () => {
        const user = userEvent.setup();
        render(<PasswordInput id="mdp" value="Secret1!" onChange={() => {}} />);

        await user.click(screen.getByRole('button', { name: 'Afficher le mot de passe' }));
        expect(document.getElementById('mdp')).toHaveAttribute('type', 'text');
        expect(screen.getByRole('button', { name: 'Masquer le mot de passe' })).toHaveAttribute('aria-pressed', 'true');

        await user.click(screen.getByRole('button', { name: 'Masquer le mot de passe' }));
        expect(document.getElementById('mdp')).toHaveAttribute('type', 'password');
    });

    it('ne soumet pas le formulaire qui l entoure', async () => {
        // Un <button> sans type="button" est un bouton de soumission : le
        // cliquer pour voir son mot de passe enverrait le formulaire.
        const user = userEvent.setup();
        const onSubmit = vi.fn((e) => e.preventDefault());
        render(
            <form onSubmit={onSubmit}>
                <PasswordInput id="mdp" value="" onChange={() => {}} />
            </form>
        );

        await user.click(screen.getByRole('button', { name: 'Afficher le mot de passe' }));

        expect(onSubmit).not.toHaveBeenCalled();
    });

    it('transmet la saisie au parent', async () => {
        const user = userEvent.setup();
        const onChange = vi.fn();
        render(<PasswordInput id="mdp" value="" onChange={onChange} />);

        await user.type(document.getElementById('mdp'), 'a');

        expect(onChange).toHaveBeenCalled();
    });
});
