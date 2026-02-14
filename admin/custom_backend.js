// Custom Backend for Decap CMS using PHP API
class MyCustomBackend {
  constructor(config, options = {}) {
    this.config = config;
    this.options = options;
    this.apiUrl = './api.php';
  }

  authComponent() {
    // Try to get React and h from window or CMS global
    const React = window.React || (window.CMS ? window.CMS.React : null);
    const h = window.h || (window.CMS ? window.CMS.h : null);

    if (!React || !h) {
      console.error('CMS: React or h (hyperscript) not found.');
      return null;
    }

    return class Login extends React.Component {
      constructor(props) {
        super(props);
        this.state = { username: '', password: '' };
      }

      handleLogin = (e) => {
        e.preventDefault();
        this.props.onLogin(this.state);
      };

      render() {
        return h('div', {
          style: {
            display: 'flex',
            justifyContent: 'center',
            alignItems: 'center',
            height: '100vh',
            backgroundColor: '#f1f5f9',
            fontFamily: 'Inter, system-ui, sans-serif'
          }
        }, [
          h('form', {
            onSubmit: this.handleLogin,
            style: {
              backgroundColor: 'white',
              padding: '40px',
              borderRadius: '12px',
              boxShadow: '0 10px 25px -5px rgba(0, 0, 0, 0.1)',
              width: '100%',
              maxWidth: '400px',
              display: 'flex',
              flexDirection: 'column',
              gap: '20px'
            }
          }, [
            h('h1', {
              style: {
                fontSize: '24px',
                fontWeight: '900',
                textAlign: 'center',
                margin: '0 0 10px 0',
                color: '#0f172a',
                letterSpacing: '-0.025em'
              }
            }, 'SBA INTERACTIVE'),
            h('div', { style: { display: 'flex', flexDirection: 'column', gap: '8px' } }, [
              h('label', { style: { fontSize: '14px', fontWeight: '600', color: '#475569' } }, 'Username'),
              h('input', {
                type: 'text',
                value: this.state.username,
                onChange: (e) => this.setState({ username: e.target.value }),
                placeholder: 'admin',
                style: {
                  padding: '12px',
                  borderRadius: '6px',
                  border: '1px solid #e2e8f0',
                  fontSize: '16px'
                }
              }),
            ]),
            h('div', { style: { display: 'flex', flexDirection: 'column', gap: '8px' } }, [
              h('label', { style: { fontSize: '14px', fontWeight: '600', color: '#475569' } }, 'Password'),
              h('input', {
                type: 'password',
                value: this.state.password,
                onChange: (e) => this.setState({ password: e.target.value }),
                placeholder: '••••••••',
                style: {
                  padding: '12px',
                  borderRadius: '6px',
                  border: '1px solid #e2e8f0',
                  fontSize: '16px'
                }
              }),
            ]),
            h('button', {
              type: 'submit',
              disabled: this.props.inProgress,
              style: {
                padding: '12px',
                backgroundColor: '#0f172a',
                color: 'white',
                border: 'none',
                borderRadius: '6px',
                fontSize: '16px',
                fontWeight: '600',
                cursor: 'pointer',
                marginTop: '10px',
                opacity: this.props.inProgress ? 0.7 : 1
              }
            }, this.props.inProgress ? 'Signing in...' : 'Sign In'),
            this.props.error && h('p', {
              style: {
                color: '#ef4444',
                fontSize: '14px',
                textAlign: 'center',
                margin: '10px 0 0 0',
                fontWeight: '500'
              }
            }, 'Invalid credentials. Please try again.')
          ])
        ]);
      }
    };
  }

  authenticate(creds) {
    return fetch(`${this.apiUrl}?action=login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(creds),
    })
    .then((response) => {
      if (!response.ok) throw new Error('Auth failed');
      return response.json();
    })
    .then((data) => {
      if (data.error) throw new Error(data.error);
      localStorage.setItem('cms_token', data.token);
      return data.user;
    });
  }

  currentUser() {
    const token = localStorage.getItem('cms_token');
    if (!token) return Promise.resolve(null);

    return fetch(`${this.apiUrl}?action=user`, {
      headers: { 'Authorization': `Bearer ${token}` }
    })
    .then((response) => {
      if (!response.ok) {
        localStorage.removeItem('cms_token');
        return null;
      }
      return response.json();
    })
    .then((user) => {
      if (!user || user.error) {
        localStorage.removeItem('cms_token');
        return null;
      }
      return user;
    })
    .catch(() => {
      localStorage.removeItem('cms_token');
      return null;
    });
  }

  getToken() {
    return Promise.resolve(localStorage.getItem('cms_token'));
  }

  restoreUser() {
    return this.currentUser();
  }

  logout() {
    localStorage.removeItem('cms_token');
    return Promise.resolve();
  }

  viewURL() {
    return '/';
  }

  entriesByFolder(collection) {
    const collectionName = collection.get('name');
    return fetch(`${this.apiUrl}?action=list_entries&collection=${collectionName}`)
      .then((response) => response.json())
      .then((entries) => {
        if (!Array.isArray(entries)) return [];
        return entries.map(entry => ({
          data: entry.data,
          slug: entry.slug,
          path: entry.slug,
        }));
      });
  }

  entriesByFiles(collection) {
    const files = collection.get('files').toJS();
    const collectionName = collection.get('name');
    return Promise.all(files.map(file => 
      fetch(`${this.apiUrl}?action=get_entry&collection=${collectionName}&slug=${file.name}`)
        .then(res => res.json())
        .then(data => ({
          data: data,
          label: file.label,
          path: file.file,
          slug: file.name
        }))
        .catch(() => null)
    )).then(results => results.filter(Boolean));
  }

  getEntry(collection, slug) {
    const collectionName = collection.get('name');
    return fetch(`${this.apiUrl}?action=get_entry&collection=${collectionName}&slug=${slug}`)
      .then((response) => response.json())
      .then(data => ({
        data: data,
        slug: slug,
      }));
  }

  persistEntry(entry) {
    const data = entry.get('data').toJS();
    const token = localStorage.getItem('cms_token');
    return fetch(`${this.apiUrl}?action=save_entry`, {
      method: 'POST',
      headers: { 
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
      },
      body: JSON.stringify({
        collection: entry.get('collection'),
        slug: entry.get('slug'),
        data: data
      }),
    })
    .then((response) => response.json());
  }

  getMedia() {
    return fetch(`${this.apiUrl}?action=get_media`)
      .then((response) => response.json());
  }

  persistMedia(mediaFile) {
    const formData = new FormData();
    formData.append('file', mediaFile.fileObj);
    const token = localStorage.getItem('cms_token');
    
    return fetch(`${this.apiUrl}?action=upload_media`, {
      method: 'POST',
      headers: { 
        'Authorization': `Bearer ${token}`
      },
      body: formData,
    })
    .then((response) => response.json())
    .then(data => ({
      id: data.url,
      name: mediaFile.name,
      size: mediaFile.size,
      url: data.url,
      path: data.path
    }));
  }

  deleteFile(path) {
    const token = localStorage.getItem('cms_token');
    return fetch(`${this.apiUrl}?action=delete_file`, {
      method: 'POST',
      headers: { 
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
      },
      body: JSON.stringify({ path }),
    })
    .then((response) => response.json());
  }
}

window.MyCustomBackend = MyCustomBackend;
