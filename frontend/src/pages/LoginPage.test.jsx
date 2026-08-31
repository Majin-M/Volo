import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import LoginPage from './LoginPage';

// Mock navigate
const mockNavigate = vi.fn();
vi.mock('react-router-dom', async (importOriginal) => {
    const actual = await importOriginal();
    return { ...actual, useNavigate: () => mockNavigate };
});

// Mock auth context
const mockLogin = vi.fn();
vi.mock('../contexts/AuthContext', () => ({
    useAuth: () => ({ login: mockLogin }),
}));

// Mock toast context
const mockAddToast = vi.fn();
vi.mock('../contexts/ToastContext', () => ({
    useToast: () => ({ addToast: mockAddToast }),
}));

// Mock API
vi.mock('../api/api', () => ({
    apiCall: vi.fn(),
}));

import { apiCall } from '../api/api';

function renderLoginPage() {
    return render(
        <MemoryRouter>
            <LoginPage />
        </MemoryRouter>,
    );
}

describe('LoginPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('affiche le formulaire de connexion', () => {
        renderLoginPage();
        expect(screen.getByLabelText('Email')).toBeInTheDocument();
        expect(screen.getByLabelText('Mot de passe')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Se connecter' })).toBeInTheDocument();
    });

    it('affiche une erreur si les champs sont vides', async () => {
        const user = userEvent.setup();
        renderLoginPage();
        await user.click(screen.getByRole('button', { name: 'Se connecter' }));
        expect(screen.getByText('Email et mot de passe sont requis.')).toBeInTheDocument();
        expect(apiCall).not.toHaveBeenCalled();
    });

    it('affiche une erreur si email invalide', async () => {
        const user = userEvent.setup();
        renderLoginPage();
        await user.type(screen.getByLabelText('Email'), 'pas-un-email');
        await user.type(screen.getByLabelText('Mot de passe'), 'password');
        await user.click(screen.getByRole('button', { name: 'Se connecter' }));
        expect(screen.getByText("L'adresse email est invalide.")).toBeInTheDocument();
        expect(apiCall).not.toHaveBeenCalled();
    });

    it('appelle l\'API et redirige en cas de succes', async () => {
        const user = userEvent.setup();
        apiCall.mockResolvedValueOnce({
            data: { user: { id: 1, email: 'test@volo.fr', firstName: 'Test' } },
        });

        renderLoginPage();
        await user.type(screen.getByLabelText('Email'), 'test@volo.fr');
        await user.type(screen.getByLabelText('Mot de passe'), 'Passw0rd!');
        await user.click(screen.getByRole('button', { name: 'Se connecter' }));

        await waitFor(() => {
            expect(apiCall).toHaveBeenCalledWith('/auth/login', {
                method: 'POST',
                body: JSON.stringify({ username: 'test@volo.fr', password: 'Passw0rd!' }),
            });
            expect(mockLogin).toHaveBeenCalledWith({
                id: 1,
                email: 'test@volo.fr',
                firstName: 'Test',
            });
            expect(mockNavigate).toHaveBeenCalledWith('/panier');
        });
    });

    it('affiche le message d\'erreur de l\'API', async () => {
        const user = userEvent.setup();
        apiCall.mockRejectedValueOnce(new Error('Identifiants invalides.'));

        renderLoginPage();
        await user.type(screen.getByLabelText('Email'), 'test@volo.fr');
        await user.type(screen.getByLabelText('Mot de passe'), 'mauvais');
        await user.click(screen.getByRole('button', { name: 'Se connecter' }));

        await waitFor(() => {
            expect(screen.getByText('Identifiants invalides.')).toBeInTheDocument();
        });
    });

    it('affiche le message de rate limiting (429)', async () => {
        const user = userEvent.setup();
        apiCall.mockRejectedValueOnce(
            new Error('Trop de tentatives. Veuillez reessayer dans quelques minutes.'),
        );

        renderLoginPage();
        await user.type(screen.getByLabelText('Email'), 'test@volo.fr');
        await user.type(screen.getByLabelText('Mot de passe'), 'test');
        await user.click(screen.getByRole('button', { name: 'Se connecter' }));

        await waitFor(() => {
            expect(
                screen.getByText('Trop de tentatives. Veuillez reessayer dans quelques minutes.'),
            ).toBeInTheDocument();
        });
    });

    it('desactive le bouton pendant le chargement', async () => {
        const user = userEvent.setup();
        let resolveApi;
        apiCall.mockImplementationOnce(
            () => new Promise((resolve) => { resolveApi = resolve; }),
        );

        renderLoginPage();
        await user.type(screen.getByLabelText('Email'), 'test@volo.fr');
        await user.type(screen.getByLabelText('Mot de passe'), 'Passw0rd!');
        await user.click(screen.getByRole('button', { name: 'Se connecter' }));

        expect(screen.getByRole('button', { name: 'Connexion...' })).toBeDisabled();

        resolveApi({ data: { user: { id: 1, email: 'test@volo.fr' } } });

        await waitFor(() => {
            expect(mockNavigate).toHaveBeenCalled();
        });
    });
});
