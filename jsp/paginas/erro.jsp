<%@ page language="java" contentType="text/html; charset=UTF-8" pageEncoding="UTF-8"%>
<%
    // Encerrar a sessão
    session.invalidate();
%>

<!DOCTYPE html>
<html>
<head>
    <title>Erro</title>
    <style>
        body { 
            font-family: Arial; 
            text-align: center; 
            margin-top: 100px; 
        }
        button { 
            padding: 10px 20px; 
            background: #007BFF; 
            color: white; 
            border: none; 
            cursor: pointer; 
        }
    </style>
</head>
<body>
    <h2>Acesso Negado</h2>
    <p>Não tem permissão para aceder a esta página.</p>
    <button onclick="window.location.href='index.jsp'">Voltar</button>
</body>
</html>
