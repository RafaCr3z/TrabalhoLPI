<%@ page language="java" contentType="text/html; charset=UTF-8" pageEncoding="UTF-8"%>

<%
    // Invalidar a sessão
    session.invalidate();
    
    // Redirecionar para a página inicial
    response.sendRedirect("index.jsp");
%>
