import { Navigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

const PrivateRoute = ({ children }) => {
    const { isAuthenticated, isLoading } = useAuth();

    if (isLoading) {
        return (
            <div style={{
                display: 'flex',
                justifyContent: 'center',
                alignItems: 'center',
                minHeight: '50vh',
                color: '#5F4C42',
            }}>
                Chargement...
            </div>
        );
    }

    if (!isAuthenticated) {
        return <Navigate to="/connexion" replace />;
    }

    return children;
};

export default PrivateRoute;
